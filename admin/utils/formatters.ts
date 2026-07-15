/** All monetary values are integers in minor units (pesewas/cents). */
export function formatMoney(minorUnits: number, currency: 'GHS' | 'USD' = 'GHS'): string {
  const major = minorUnits / 100
  return new Intl.NumberFormat(currency === 'GHS' ? 'en-GH' : 'en-US', {
    style: 'currency',
    currency,
  }).format(major)
}
