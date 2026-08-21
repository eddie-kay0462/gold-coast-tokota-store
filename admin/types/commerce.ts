import type { Currency, Money, Timestamp } from './common'

/** README Data Models — Order.status, verbatim. */
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
export type DeliveryProvider = 'yango' | 'dhl'

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
  country: string
  countryCode: string
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
  paymentGateway: PaymentGateway
  paymentMethod: PaymentMethod
  paymentReference: string
  deliveryProvider: DeliveryProvider
  deliveryReference: string | null
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

export type ShipmentStatus =
  | 'awaiting_pickup'
  | 'in_transit'
  | 'customs'
  | 'out_for_delivery'
  | 'delivered'
  | 'exception'

export interface Shipment {
  id: number
  orderId: number
  orderReference: string
  provider: DeliveryProvider
  trackingReference: string
  status: ShipmentStatus
  destination: string
  destinationCountryCode: string
  /** From the PDF's shipping SLA table, per destination band. */
  etaLabel: string
  dispatchedAt: Timestamp | null
  updatedAt: Timestamp
}
