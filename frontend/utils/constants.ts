export const CURRENCIES = ['GHS', 'USD'] as const
export type Currency = (typeof CURRENCIES)[number]

export const INVENTORY_POLL_INTERVAL_MS = 20_000
export const RESERVATION_TTL_MINUTES = 15
