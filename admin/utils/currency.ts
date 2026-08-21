import type { Currency, FxRate, Money } from '~/types'

/**
 * USD is DERIVED, never stored.
 *
 * README Feature 2 is explicit: products carry `base_price_ghs` only, and the
 * dollar figure is computed at read time from the cached FX rate. The one
 * exception is an order, which snapshots `fx_rate_applied` at checkout so a
 * historic total never moves when the rate does — hence `usdFromOrder` takes
 * the locked rate rather than reaching for the live one.
 */
export function usdFrom(ghs: Money, rate: FxRate | number): Money {
  const r = typeof rate === 'number' ? rate : rate.rate
  return { amount: Math.round(ghs.amount * r), currency: 'USD' }
}

export function usdFromOrder(ghs: Money, lockedRate: number | null): Money | null {
  return lockedRate == null ? null : { amount: Math.round(ghs.amount * lockedRate), currency: 'USD' }
}

const locales: Record<Currency, string> = { GHS: 'en-GH', USD: 'en-US' }

/** Money is integer minor units everywhere; divide only at the display edge. */
export function formatMoney(value: Money): string {
  return new Intl.NumberFormat(locales[value.currency], {
    style: 'currency',
    currency: value.currency,
  }).format(value.amount / 100)
}

/** Compact form for metric tiles: GH₵1.2M rather than GH₵1,240,000.00 */
export function formatMoneyCompact(value: Money): string {
  return new Intl.NumberFormat(locales[value.currency], {
    style: 'currency',
    currency: value.currency,
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(value.amount / 100)
}

export function formatRate(rate: FxRate): string {
  return `GH₵1 = $${rate.rate.toFixed(4)}`
}
