import { useCurrencyStore } from '~/stores/currency'

/**
 * Loads the display currency the visitor last chose, and the GHS->USD rate
 * used to derive every USD price on the storefront.
 *
 * USD is never a stored field (README Feature 2) — it is always
 * `base_price_ghs × rate`, computed at display time. Until a rate loads it is
 * 0, which is why `PriceDisplay` falls back to GHS rather than rendering a
 * price of $0. The rate here is for *display only*; checkout locks its own
 * rate server-side.
 *
 * The server render awaits the rate so a USD visitor gets USD in the first
 * paint, and Pinia carries it to the client. If that request failed, the
 * client tries again instead of inheriting the failure. This plugin used to
 * go through `useAsyncData`, whose cached payload kept a failed server fetch
 * as the answer for the whole visit, and the GHS|USD switch then did nothing.
 */
export default defineNuxtPlugin(async () => {
  const currency = useCurrencyStore()

  currency.hydrate()

  if (import.meta.server) {
    await currency.loadFxRate()
    return
  }

  // Not awaited: the page shouldn't wait on the rate. Prices repaint in USD
  // when it lands.
  if (!currency.canConvert) void currency.loadFxRate()
})
