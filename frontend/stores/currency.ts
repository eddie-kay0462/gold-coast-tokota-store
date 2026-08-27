import { defineStore } from 'pinia'
import type { Currency } from '~/utils/constants'

/**
 * Cookie the display currency is mirrored into. Same treatment as the cart
 * (`gct_cart`) and for the same reason: it has to be readable during SSR, or
 * the server renders GHS and the client repaints into USD after hydration.
 */
const CURRENCY_COOKIE = 'gct_currency'
const CURRENCY_COOKIE_MAX_AGE = 60 * 60 * 24 * 365

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
