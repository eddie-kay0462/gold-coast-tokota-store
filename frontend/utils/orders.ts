/**
 * Order shapes returned by `GET /api/v1/orders/{id}`.
 *
 * That endpoint does not exist yet (README Feature 4 — no CheckoutController,
 * no webhook receiver), but the fields below are the `Order` / `OrderItem` data
 * models from the README, so the confirmation page is typed against the real
 * contract rather than `any`.
 */
export type ApiOrderItem = {
  id: number
  product_id: number
  name: string
  slug?: string
  image?: string | null
  variant_label?: string | null
  quantity: number
  /** Minor units, in the order's currency. */
  unit_price: number
}

export type OrderStatus =
  | 'pending'
  | 'paid'
  | 'processing'
  | 'shipped'
  | 'delivered'
  | 'cancelled'
  | 'refunded'
  | 'inventory_conflict'

export type ApiOrder = {
  id: number
  reference?: string
  status: OrderStatus
  currency: 'GHS' | 'USD'
  /** Snapshotted at checkout for USD orders; null for GHS. */
  fx_rate_applied: number | null
  subtotal: number
  shipping_cost: number
  tax: number
  total: number
  payment_gateway: 'paystack' | 'stripe' | null
  delivery_provider: 'yango' | 'dhl' | null
  delivery_reference: string | null
  shipping_address: {
    full_name?: string
    line1?: string
    city?: string
    region?: string
    postcode?: string
    country?: string
  } | null
  items: ApiOrderItem[]
  created_at: string
}

/** Statuses that are still waiting on the payment webhook. */
export function isAwaitingPayment(status: OrderStatus): boolean {
  return status === 'pending'
}

export function deliveryProviderLabel(provider: ApiOrder['delivery_provider']): string {
  if (provider === 'yango') return 'Yango'
  if (provider === 'dhl') return 'DHL'
  return 'To be confirmed'
}
