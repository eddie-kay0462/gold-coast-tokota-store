export const CURRENCIES = ['GHS', 'USD'] as const
export type Currency = (typeof CURRENCIES)[number]

export const INVENTORY_POLL_INTERVAL_MS = 20_000
export const RESERVATION_TTL_MINUTES = 15

/** Order-status polling on the confirmation page (README Feature 4). */
export const ORDER_POLL_INTERVAL_MS = 5_000
/** Stop after ~2 minutes; a webhook that late is an incident, not a delay. */
export const ORDER_POLL_MAX_ATTEMPTS = 24
