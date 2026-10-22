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
   * and is NEVER stored (README Feature 2), hence `basePriceUsd` is absent
   * here by design, and computed by `usdFrom()` in utils/currency.ts.
   */
  basePriceGhs: Money
  /** Raw stored references, e.g. `/products/domfo/1-tan.webp`. Round-tripped on save. */
  images: string[]
  /**
   * The same photos as absolute URLs the admin can load. `images` holds
   * storefront-relative paths, which on the admin's origin load its own HTML.
   */
  imageUrls?: string[]
  /** Photos grouped by colourway, in swatch order. `keys` are the stored references, index-aligned with `urls`. */
  colourPhotos?: { colour: string, hex: string | null, urls: string[], keys?: string[] }[]
  isActive: boolean
  isFeatured: boolean
  /** What couriers and customs need; required for international orders while DHL is on. */
  shipping?: ProductShipping
  /** Rolled up across variants, for the list view. */
  totalAvailable: number
  totalReserved: number
  lowStock: boolean
  createdAt: Timestamp
  updatedAt: Timestamp
}

export interface ProductShipping {
  weightGrams: number | null
  lengthMm: number | null
  widthMm: number | null
  heightMm: number | null
  /** Harmonized System code for the export declaration, e.g. 6403.99. */
  hsCode: string | null
  originCountry: string
  customsDescription: string | null
  readyForInternational: boolean
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

/** One line of a variant's stock history, `GET /admin/inventory/{id}/movements`. */
export type InventoryMovementReason = 'hold' | 'hold_released' | 'hold_expired' | 'sale' | 'adjustment'

export interface InventoryMovement {
  id: number
  inventoryItemId: number
  reason: InventoryMovementReason
  deltaAvailable: number
  deltaReserved: number
  quantityAvailableAfter: number
  quantityReservedAfter: number
  channel: 'web' | 'whatsapp' | null
  orderReference: string | null
  adminName: string | null
  note: string | null
  createdAt: Timestamp
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
