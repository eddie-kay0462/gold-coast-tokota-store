import type { Currency, Money, Timestamp } from './common'

/** README Data Models: Order.status, verbatim. */
export type OrderStatus =
  | 'pending'
  | 'paid'
  | 'processing'
  | 'shipped'
  | 'delivered'
  | 'cancelled'
  | 'refunded'
  | 'inventory_conflict'

export const ORDER_STATUSES: OrderStatus[] = [
  'pending', 'paid', 'processing', 'shipped',
  'delivered', 'cancelled', 'refunded', 'inventory_conflict',
]

/** The fulfilment pipeline shown on /orders/board (Figma "Deals" frame). */
export const ORDER_BOARD_COLUMNS: OrderStatus[] = [
  'pending', 'paid', 'processing', 'shipped', 'delivered',
]

export type PaymentGateway = 'paystack' | 'stripe'
/**
 * Who carries a parcel. `manual` is staff booking a courier themselves; every
 * zone falls back to it while its courier API is switched off.
 */
export type DeliveryProvider = 'yango' | 'swoove' | 'dhl' | 'manual'

/** Decided by the API from the address: the Accra pin zone, the rest of Ghana, abroad. */
export type ShippingZone = 'accra' | 'ghana' | 'international'

/**
 * Paystack methods from the brand PDF's "Payment Gateway Accounts" section.
 * Mobile money is a first-class channel in Ghana, not an afterthought.
 */
export type PaymentMethod =
  | 'card'
  | 'mtn_momo'
  | 'telecel_cash'
  | 'airteltigo_money'
  | 'bank_transfer'

export const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = {
  card: 'Card',
  mtn_momo: 'MTN MoMo',
  telecel_cash: 'Telecel Cash',
  airteltigo_money: 'AirtelTigo Money',
  bank_transfer: 'Bank transfer',
}

export interface ShippingAddress {
  line1: string
  line2?: string
  city: string
  region: string
  postcode?: string
  /** ISO code from the API (e.g. `GH`); fixtures used a display name. */
  country: string
  /** Fixture-only; the API sends `country` as the ISO code. */
  countryCode?: string
  lat?: number | null
  lng?: number | null
}

export interface OrderItem {
  id: number
  productId: number
  productName: string
  sku: string
  variantLabel: string
  quantity: number
  unitPrice: Money
}

export interface Order {
  id: number
  reference: string
  customerId: number | null
  customerName: string
  customerEmail: string
  isGuest: boolean
  currency: Currency
  /**
   * Snapshotted at checkout for USD orders and never recomputed, so a historic
   * order is immune to later rate moves (README Feature 2 acceptance criteria).
   * Null for GHS orders.
   */
  fxRateApplied: number | null
  items: OrderItem[]
  subtotal: Money
  shippingCost: Money
  tax: Money
  total: Money
  status: OrderStatus
  /** Where it was placed. A WhatsApp order was built in the inbox and paid by link. */
  channel: 'web' | 'whatsapp'
  whatsappConversationId: number | null
  paymentGateway: PaymentGateway
  paymentMethod: PaymentMethod
  paymentReference: string
  deliveryProvider: DeliveryProvider | null
  deliveryReference: string | null
  shippingZone?: ShippingZone | null
  /** How delivery was priced at checkout. */
  shippingOption?: {
    carrier: DeliveryProvider
    label: string
    source: 'live' | 'fallback' | 'flat'
    quotedRaw: number | null
    quotedCurrency: string | null
    bufferPercent: number
    free: boolean
  } | null
  /** The live delivery booking. Loaded on the list and the detail. */
  shipment?: Shipment | null
  shippingAddress: ShippingAddress
  /** Set when an inbox thread was linked to this order. */
  whatsappThreadId: string | null
  placedAt: Timestamp
  updatedAt: Timestamp
}

export type ReturnReason =
  | 'defective'
  | 'wrong_item'
  | 'damaged_in_transit'
  | 'size_exchange'

export type ReturnStatus =
  | 'requested'
  | 'approved'
  | 'rejected'
  | 'received'
  | 'refunded'
  | 'exchanged'

/**
 * Returns & Exchanges, per the brand PDF's policy:
 *   - 7-day window from delivery
 *   - custom-made / personalised items are non-returnable
 *   - refunds process in 7–14 business days to the original method
 *   - customer pays return shipping on a size exchange unless we erred
 */
export interface ReturnRequest {
  id: number
  orderId: number
  orderReference: string
  customerName: string
  reason: ReturnReason
  status: ReturnStatus
  itemsSummary: string
  /** False when the order contains a custom/personalised item. */
  isEligible: boolean
  ineligibleReason: string | null
  refundAmount: Money | null
  /** Deadline computed as deliveredAt + 7 days. */
  windowClosesAt: Timestamp
  requestedAt: Timestamp
  resolvedAt: Timestamp | null
  notes: string
}

/** The API's canonical shipment states; every courier's own statuses map onto these. */
export type ShipmentStatus =
  | 'pending'
  | 'booking'
  | 'awaiting_pickup'
  | 'in_transit'
  | 'out_for_delivery'
  | 'delivered'
  | 'failed'
  | 'cancelled'
  | 'returned'

export const SHIPMENT_STATUSES: ShipmentStatus[] = [
  'pending', 'booking', 'awaiting_pickup', 'in_transit', 'out_for_delivery',
  'delivered', 'failed', 'cancelled', 'returned',
]

export interface ShipmentEvent {
  id: number
  status: ShipmentStatus | null
  carrierStatus: string | null
  source: 'poll' | 'webhook' | 'admin' | 'system'
  message: string | null
  occurredAt: Timestamp
}

/** One courier booking for an order. A retry or carrier hand-over is a new one. */
export interface Shipment {
  /** The shipment's uuid; the API's route key. */
  id: string
  zone: ShippingZone
  carrier: DeliveryProvider
  serviceCode: string | null
  attempt: number
  status: ShipmentStatus
  carrierStatus: string | null
  carrierReference: string | null
  trackingUrl: string | null
  /** DHL's pickup booking number. */
  pickupReference: string | null
  courierName: string | null
  notes: string | null
  failureReason: string | null
  quotedCost: Money | null
  actualCost: Money | null
  hasLabel: boolean
  hasInvoice: boolean
  packedAt: Timestamp | null
  dispatchedAt: Timestamp | null
  deliveredAt: Timestamp | null
  cancelledAt: Timestamp | null
  createdAt: Timestamp
  events?: ShipmentEvent[]
}

/** A Ghana region's flat delivery rate, for orders outside the Accra zone. */
export interface ShippingZoneRate {
  region: string
  amount: Money
  isActive: boolean
  updatedBy: string | null
  updatedAt: Timestamp | null
}

export interface ShippingSettings {
  accraPolygon: Record<string, unknown> | null
  accraPolygonSummary: {
    polygons: number
    points: number
    bounds: { south: number; north: number; west: number; east: number }
  } | null
  freeShippingThreshold: Money | null
  updatedAt: Timestamp | null
}
