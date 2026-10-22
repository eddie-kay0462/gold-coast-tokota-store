/** All monetary values are integers in minor units (pesewas/cents). */
export function formatMoney(
  minorUnits: number,
  currency: 'GHS' | 'USD',
  options: {
    /**
     * Product-card style: the narrow symbol (`₵700` rather than `GH₵700.00`),
     * as drawn on the listing page. Minor units are still shown whenever the
     * amount isn't a whole major unit; an FX-derived USD price is almost never
     * round, and truncating it there would misstate the price.
     */
    compact?: boolean
  } = {},
): string {
  const major = minorUnits / 100
  const isWhole = minorUnits % 100 === 0

  return new Intl.NumberFormat(currency === 'GHS' ? 'en-GH' : 'en-US', {
    style: 'currency',
    currency,
    ...(options.compact
      ? {
          currencyDisplay: 'narrowSymbol' as const,
          ...(isWhole ? { minimumFractionDigits: 0, maximumFractionDigits: 0 } : {}),
        }
      : {}),
  }).format(major)
}

/**
 * A status as a customer reads it: `inventory_conflict` → "Inventory Conflict",
 * `pending` → "Pending". The API sends machine values; this is the one place
 * they become words, so every badge reads the same.
 */
export function formatStatus(status: string): string {
  return status
    .split(/[_\s-]+/)
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ')
}
