import { useCurrencyStore } from '~/stores/currency'

/**
 * Loads the display currency the visitor last chose, and the GHS->USD rate
 * used to derive every USD price on the storefront.
 *
 * USD is never a stored field (README Feature 2) — it is always
 * `base_price_ghs × rate`, computed at display time. Until this runs the rate
 * is 0, which is why `PriceDisplay` falls back to GHS rather than rendering a
 * price of $0. The rate here is for *display only*; checkout locks its own
 * rate server-side.
 */
export default defineNuxtPlugin(async () => {
  const config = useRuntimeConfig()
  const currency = useCurrencyStore()

  currency.hydrate()

  // A missing rate is not an error state for the page — the storefront simply
  // stays in GHS — so this catches rather than throwing an SSR 500.
  //
  // The catch resolves to `{ data: null }` rather than plain `null`: useAsyncData
  // warns when a handler resolves to null or undefined, because it cannot tell
  // that apart from a handler that forgot to return, and it re-runs the request
  // on the client.
  type FxRatePayload = { rate: number | string, fetched_at?: string, created_at?: string }

  const { data } = await useAsyncData('fx-rate', () =>
    $fetch<{ data: FxRatePayload | null }>(`${config.public.apiBase}/fx-rate`)
      .catch(() => ({ data: null })),
  )

  const payload = data.value?.data
  if (!payload) return

  const rate = Number(payload.rate)
  if (!Number.isFinite(rate) || rate <= 0) return

  currency.setFxRate(rate, payload.fetched_at ?? payload.created_at ?? new Date().toISOString())
})
