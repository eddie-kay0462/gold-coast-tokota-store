import type {
  Order, OrderItem, OrderStatus, PaymentMethod, ReturnRequest, Shipment, ShippingAddress,
  ShippingSettings, ShippingZoneRate,
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

/** Every courier API is switched off until credentials arrive, so fixtures dispatch by hand. */
function fixtureShipment(status: OrderStatus, isGh: boolean, placedAt: string): Shipment | null {
  if (!['paid', 'processing', 'shipped', 'delivered'].includes(status)) return null
  const shipped = status === 'shipped' || status === 'delivered'
  return {
    id: `fx-${placedAt}`,
    zone: isGh ? 'ghana' : 'international',
    carrier: 'manual',
    serviceCode: null,
    attempt: 1,
    status: status === 'delivered' ? 'delivered' : shipped ? 'in_transit' : 'pending',
    carrierStatus: null,
    carrierReference: shipped ? `VIP-${int(10 ** 5, 9 * 10 ** 5)}` : null,
    trackingUrl: null,
    pickupReference: null,
    courierName: shipped ? (isGh ? 'VIP Bus Parcels' : 'DHL counter') : null,
    notes: null,
    failureReason: null,
    quotedCost: null,
    actualCost: shipped ? ghs(isGh ? 3000 : 32000) : null,
    hasLabel: false,
    hasInvoice: false,
    packedAt: null,
    dispatchedAt: shipped ? placedAt : null,
    deliveredAt: status === 'delivered' ? placedAt : null,
    cancelledAt: null,
    createdAt: placedAt,
    events: [],
  }
}

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
      // rate for USD orders, never re-derived from today's rate.
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
    deliveryProvider: 'manual',
    deliveryReference: dispatched ? `VIP-${int(10 ** 5, 9 * 10 ** 5)}` : null,
    shippingZone: isGh ? 'ghana' : 'international',
    shipment: fixtureShipment(status, isGh, placedAt),
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
  size_exchange: 'Runs large. Customer requests one size down.',
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

/**
 * The Shipments screen lists orders, each with its live shipment, exactly as
 * `GET /admin/shipments` returns them.
 */
export const shipments: Order[] = orders.filter((o) => ['paid', 'processing', 'shipped', 'delivered'].includes(o.status))

/** Ghana's sixteen regions, a few priced, as `GET /admin/shipping/rates` lists them. */
const priced: Record<string, number> = { 'Greater Accra': 3000, Ashanti: 4500, Central: 4000, Eastern: 4000 }
export const shippingRates: ShippingZoneRate[] = [
  'Ahafo', 'Ashanti', 'Bono', 'Bono East', 'Central', 'Eastern',
  'Greater Accra', 'North East', 'Northern', 'Oti', 'Savannah',
  'Upper East', 'Upper West', 'Volta', 'Western', 'Western North',
].map((region) => ({
  region,
  amount: ghs(priced[region] ?? 0),
  isActive: region in priced,
  updatedBy: region in priced ? 'Akosua Owusu' : null,
  updatedAt: region in priced ? daysAgo(3) : null,
}))

export const shippingSettings: ShippingSettings = {
  accraPolygon: null,
  accraPolygonSummary: null,
  freeShippingThreshold: null,
  updatedAt: null,
}

export const shipmentAttention = {
  count: shipments.filter((o) => o.shipment?.status === 'failed' || o.shipment?.status === 'pending').length,
}
