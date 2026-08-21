import type {
  Order, OrderItem, OrderStatus, PaymentMethod, ReturnRequest, Shipment, ShippingAddress,
} from '~/types'
import { customers } from './people'
import { products } from './catalogue'
import { chance, daysAgo, ghs, int, pick, usd } from './_seed'
import { fxRate } from './catalogue'

const ghCities: [string, string][] = [
  ['Accra', 'Greater Accra'], ['Kumasi', 'Ashanti'], ['Takoradi', 'Western'],
  ['Tamale', 'Northern'], ['Cape Coast', 'Central'], ['Ho', 'Volta'],
]
const intlCities: [string, string, string, string][] = [
  ['London', 'England', 'United Kingdom', 'GB'],
  ['New York', 'NY', 'United States', 'US'],
  ['Toronto', 'ON', 'Canada', 'CA'],
  ['Berlin', 'Berlin', 'Germany', 'DE'],
  ['Lagos', 'Lagos', 'Nigeria', 'NG'],
  ['Amsterdam', 'NH', 'Netherlands', 'NL'],
]

function addressFor(countryCode: string): ShippingAddress {
  if (countryCode === 'GH') {
    const [city, region] = pick(ghCities)
    return {
      line1: `${int(1, 90)} ${pick(['Oxford St', 'Spintex Rd', 'Ring Rd East', 'Haatso Rd', 'Liberation Rd'])}`,
      city, region, country: 'Ghana', countryCode: 'GH',
    }
  }
  const [city, region, country, cc] = pick(intlCities)
  return {
    line1: `${int(1, 200)} ${pick(['High St', 'Church Rd', 'Market Sq', 'Park Lane'])}`,
    city, region, postcode: `${int(10000, 99999)}`, country, countryCode: cc,
  }
}

const statusWeights: OrderStatus[] = [
  'pending', 'pending', 'paid', 'paid', 'paid', 'processing', 'processing',
  'shipped', 'shipped', 'delivered', 'delivered', 'delivered', 'delivered',
  'cancelled', 'refunded', 'inventory_conflict',
]

const ghMethods: PaymentMethod[] = ['mtn_momo', 'mtn_momo', 'card', 'telecel_cash', 'airteltigo_money', 'bank_transfer']

export const orders: Order[] = Array.from({ length: 72 }, (_, i) => {
  const customer = pick(customers)
  const isGh = customer.countryCode === 'GH'
  const currency = isGh ? 'GHS' : 'USD'
  const status = pick(statusWeights)
  const address = addressFor(customer.countryCode)

  const items: OrderItem[] = Array.from({ length: int(1, 3) }, (_, j) => {
    const p = pick(products)
    const qty = int(1, 2)
    return {
      id: 9000 + i * 3 + j,
      productId: p.id,
      productName: p.name,
      sku: p.sku,
      variantLabel: `Size ${int(38, 45)} · ${pick(['Tan', 'Black', 'Ochre', 'Indigo'])}`,
      quantity: qty,
      // Unit price is held in the order's currency, converted at the locked
      // rate for USD orders — never re-derived from today's rate.
      unitPrice: isGh
        ? ghs(p.basePriceGhs.amount)
        : usd(Math.round(p.basePriceGhs.amount * fxRate.rate)),
    }
  })

  const subtotalMinor = items.reduce((s, it) => s + it.unitPrice.amount * it.quantity, 0)
  const shippingMinor = isGh ? 3500 : Math.round(2200 * (currency === 'USD' ? 1 : 1))
  const taxMinor = 0
  const mk = (n: number) => (isGh ? ghs(n) : usd(n))

  const placedAt = daysAgo(int(0, 45), int(0, 23))
  const dispatched = ['shipped', 'delivered'].includes(status)

  return {
    id: 3000 + i,
    reference: `GCT-${8100000 + i * 137}`,
    customerId: customer.hasAccount ? customer.id : null,
    customerName: customer.name,
    customerEmail: customer.email,
    isGuest: !customer.hasAccount,
    currency,
    fxRateApplied: isGh ? null : fxRate.rate,
    items,
    subtotal: mk(subtotalMinor),
    shippingCost: mk(shippingMinor),
    tax: mk(taxMinor),
    total: mk(subtotalMinor + shippingMinor + taxMinor),
    status,
    paymentGateway: isGh ? 'paystack' : 'stripe',
    paymentMethod: isGh ? pick(ghMethods) : 'card',
    paymentReference: isGh ? `ps_${int(10 ** 9, 9 * 10 ** 9)}` : `pi_${int(10 ** 9, 9 * 10 ** 9)}`,
    deliveryProvider: isGh ? 'yango' : 'dhl',
    deliveryReference: dispatched ? (isGh ? `YG${int(10 ** 6, 9 * 10 ** 6)}` : `DHL${int(10 ** 8, 9 * 10 ** 8)}`) : null,
    shippingAddress: address,
    whatsappThreadId: null,
    placedAt,
    updatedAt: placedAt,
  }
})

/**
 * Returns, per the PDF's Returns & Exchanges policy: a 7-day window from
 * delivery, and custom-made / personalised items are never returnable. The
 * ineligible rows exist so that rule is visible in the UI rather than implied.
 */
const returnNotes: Record<string, string> = {
  defective: 'Strap stitching came apart at the toe post after light wear.',
  wrong_item: 'Ordered the Kente Panel Slide, received the Kente Weave Thong.',
  damaged_in_transit: 'Box crushed on arrival; one sole is creased through.',
  size_exchange: 'Runs large — customer requests one size down.',
}

export const returnRequests: ReturnRequest[] = Array.from({ length: 14 }, (_, i) => {
  const order = pick(orders.filter((o) => o.status === 'delivered'))
  const reason = pick(['defective', 'wrong_item', 'damaged_in_transit', 'size_exchange'] as const)
  const isCustom = chance(0.2)
  const requestedAt = daysAgo(int(0, 20))
  return {
    id: 4000 + i,
    orderId: order.id,
    orderReference: order.reference,
    customerName: order.customerName,
    reason,
    status: pick(['requested', 'requested', 'approved', 'received', 'refunded', 'exchanged', 'rejected'] as const),
    itemsSummary: order.items.map((it) => `${it.quantity}× ${it.productName}`).join(', '),
    isEligible: !isCustom,
    ineligibleReason: isCustom
      ? 'Custom-made sandals are non-returnable under the published returns policy.'
      : null,
    refundAmount: reason === 'size_exchange' ? null : order.total,
    windowClosesAt: daysAgo(int(0, 20) - 7),
    requestedAt,
    resolvedAt: chance(0.5) ? daysAgo(int(0, 5)) : null,
    notes: returnNotes[reason]!,
  }
})

/** ETA bands come straight from the PDF's International Shipping table. */
const etaFor = (cc: string) =>
  cc === 'GH' ? '1–2 business days'
    : cc === 'NG' ? '5–10 business days'
    : ['GB', 'DE', 'NL'].includes(cc) ? '7–14 business days'
    : ['US', 'CA'].includes(cc) ? '7–14 business days'
    : '10–21 business days'

export const shipments: Shipment[] = orders
  .filter((o) => o.deliveryReference)
  .map((o, i) => ({
    id: 5000 + i,
    orderId: o.id,
    orderReference: o.reference,
    provider: o.deliveryProvider,
    trackingReference: o.deliveryReference!,
    status: o.status === 'delivered'
      ? 'delivered'
      : pick(['awaiting_pickup', 'in_transit', 'customs', 'out_for_delivery', 'exception'] as const),
    destination: `${o.shippingAddress.city}, ${o.shippingAddress.country}`,
    destinationCountryCode: o.shippingAddress.countryCode,
    etaLabel: etaFor(o.shippingAddress.countryCode),
    dispatchedAt: o.placedAt,
    updatedAt: o.updatedAt,
  }))
