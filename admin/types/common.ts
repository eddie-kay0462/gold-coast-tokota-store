/**
 * Shared primitives.
 *
 * Money is ALWAYS an integer in minor units (pesewas / cents) paired with an
 * explicit currency — README Data Models is emphatic about this, and it is the
 * one convention most likely to be broken by accident. `Money` makes the pair
 * inseparable so a bare number can never be mistaken for a price.
 */
export type Currency = 'GHS' | 'USD'

export interface Money {
  /** Integer minor units. 12550 === GH₵125.50 */
  amount: number
  currency: Currency
}

/** ISO-8601 UTC string. All timestamps in the system are UTC. */
export type Timestamp = string

/** The project's standard response envelope (README API Requirements). */
export interface Envelope<T> {
  data: T
  meta?: Record<string, unknown> | null
  errors?: { message?: string; [k: string]: unknown } | null
}

export interface Paginated<T> {
  items: T[]
  total: number
  page: number
  perPage: number
}

export interface ListQuery {
  page?: number
  perPage?: number
  search?: string
  sort?: string
  dir?: 'asc' | 'desc'
  [filter: string]: unknown
}
