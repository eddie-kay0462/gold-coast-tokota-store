import type { Money, Timestamp } from './common'

export interface Category {
  id: number
  name: string
  slug: string
  productCount: number
}

export interface Product {
  id: number
  name: string
  slug: string
  sku: string
  description: string
  categoryId: number
  categoryName: string
  /**
   * GHS base price only. USD is derived at read time from the cached FX rate
   * and is NEVER stored (README Feature 2) — hence `basePriceUsd` is absent
   * here by design, and computed by `usdFrom()` in utils/currency.ts.
   */
  basePriceGhs: Money
  images: string[]
  isActive: boolean
  isFeatured: boolean
  /** Rolled up across variants, for the list view. */
  totalAvailable: number
  totalReserved: number
  lowStock: boolean
  createdAt: Timestamp
  updatedAt: Timestamp
}

export interface InventoryItem {
  id: number
  productId: number
  productName: string
  sku: string
  /** jsonb in Postgres: { size: '42', colour: 'Tan' } */
  variantAttributes: Record<string, string>
  quantityAvailable: number
  quantityReserved: number
  reservationExpiresAt: Timestamp | null
  lowStockThreshold: number
  updatedAt: Timestamp
}

export interface FxRate {
  baseCurrency: 'GHS'
  quoteCurrency: 'USD'
  /** USD per 1 GHS. */
  rate: number
  fetchedAt: Timestamp
  source: string
  /** True once the cached rate is older than the staleness threshold. */
  isStale: boolean
}
