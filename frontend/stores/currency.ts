import { defineStore } from 'pinia'
import type { Currency } from '~/utils/constants'

/**
 * Cookie the display currency is mirrored into. Same treatment as the cart
 * (`gct_cart`) and for the same reason: it has to be readable during SSR, or
 * the server renders GHS and the client repaints into USD after hydration.
 */
const CURRENCY_COOKIE = 'gct_currency'
const CURRENCY_COOKIE_MAX_AGE = 60 * 60 * 24 * 365

type FxRatePayload = { rate: number | string, fetched_at?: string, created_at?: string }

/**
 * One request at a time, however many callers ask for the rate at once.
 * Browser only: on the server this module is shared by every concurrent
 * render, and one request's fetch must not fill another request's store.
 */
let pendingFxRequest: Promise<boolean> | null = null

export const useCurrencyStore = defineStore('currency', {
  state: () => ({
    active: 'GHS' as Currency,
    // Cached GHS->USD rate; server remains source of truth at checkout.
    fxRate: 0,
    fxRateFetchedAt: null as string | null,
  }),

  getters: {
    /**
     * Whether USD prices can actually be shown. The rate is fetched at runtime
     * (`plugins/fx-rate.ts`) and starts at 0, so anything that formats money
     * has to ask this rather than assume — multiplying by a zero rate prints
     * a confident, wrong "$0".
     */
    canConvert: (state) => state.fxRate > 0,

    /** The currency to actually render in, which is GHS whenever USD can't be derived. */
    displayCurrency: (state): Currency =>
      state.active === 'USD' && state.fxRate > 0 ? 'USD' : 'GHS',
  },

  actions: {
    setCurrency(currency: Currency) {
      this.active = currency
      this.persist()
      // Picking USD before a rate has loaded would otherwise do nothing visible,
      // so it fetches the rate itself. GHS needs no rate.
      if (currency === 'USD' && !this.canConvert) void this.loadFxRate()
    },

    /**
     * Fetches the display rate. Resolves `true` once USD can be shown.
     *
     * A failure is not an error for the page: the storefront stays in cedis.
     * That's why this catches instead of throwing, and why a later call (the
     * client retrying, or the visitor choosing USD again) can still succeed.
     */
    loadFxRate(): Promise<boolean> {
      if (this.canConvert) return Promise.resolve(true)
      if (import.meta.client && pendingFxRequest) return pendingFxRequest

      const { apiBase } = useRuntimeConfig().public

      // `retry: 0`: ofetch retries a failed GET once by default, which doubled
      // the console error whenever the API was down. Choosing USD already
      // retries.
      const request = $fetch<{ data: FxRatePayload | null }>(`${apiBase}/fx-rate`, { retry: 0 })
        .then(({ data: payload }) => {
          const rate = Number(payload?.rate)
          if (!payload || !Number.isFinite(rate) || rate <= 0) return false

          this.setFxRate(rate, payload.fetched_at ?? payload.created_at ?? new Date().toISOString())
          return true
        })
        .catch(() => false)
        .finally(() => {
          if (pendingFxRequest === request) pendingFxRequest = null
        })

      if (import.meta.client) pendingFxRequest = request
      return request
    },

    setFxRate(rate: number, fetchedAt: string) {
      this.fxRate = rate
      this.fxRateFetchedAt = fetchedAt
    },

    persist() {
      useCookie<Currency>(CURRENCY_COOKIE, {
        maxAge: CURRENCY_COOKIE_MAX_AGE,
        sameSite: 'lax',
        path: '/',
      }).value = this.active
    },

    /**
     * Called once from a plugin on both server and client. An explicit choice
     * the visitor made before always wins — nothing here infers a currency
     * from their country, which is a commercial decision, not a technical one.
     */
    hydrate() {
      const stored = useCookie<Currency>(CURRENCY_COOKIE).value
      if (stored === 'GHS' || stored === 'USD') this.active = stored
    },
  },
})
