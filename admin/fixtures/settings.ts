import type {
  AuditEntry, CommerceSettings, DeliverySettings, NotificationSettings,
  PaymentSettings, SiteSettings, WhatsappSettings,
} from '~/types'
import { diyTurnaroundTiers } from './bookings'
import { daysAgo, hoursAgo, int, pick } from './_seed'

/** The PDF's "Default Greeting Message", verbatim. */
export const WHATSAPP_GREETING = `Welcome to Gold Coast Tokota!

Thank you for contacting us. We create handcrafted sustainable footwear from recycled materials while celebrating Ghanaian culture through immersive experiences and craftsmanship.

Whether you're looking to:
• Shop our handcrafted sandals
• Book a Sandal Sip & Paint experience
• Schedule a school or group tour
• Discuss partnerships or bulk orders
• Learn more about our sustainability initiatives

We're here to help.

Our team typically responds during business hours, Monday – Saturday, 9:00 AM – 5:00 PM (GMT).

Please let us know how we can assist you today.

Gold Coast Tokota — Crafted with Purpose. Inspired by Culture.`

export const siteSettings: SiteSettings = {
  // From the PDF's Contact Us section. Note it is annotated there as
  // "(update with official number)" — surfaced as a warning in the UI.
  whatsappNumber: '+233257534297',
  whatsappDefaultMessage: 'Hi! I have a question about your sandals.',
  whatsappGreeting: WHATSAPP_GREETING,
  businessHours: 'Monday – Saturday, 9:00 AM – 5:00 PM (GMT)',
  contactEmail: 'hello@goldcoasttokota.store',
  contactPhone: '+233257534297',
  addressLine: 'Haatso, Accra, Ghana',
  instagramUrl: 'https://instagram.com/goldcoasttokota',
  heroHeadline: 'Crafted with Purpose. Inspired by Culture.',
  heroImage: null,
  diyTurnaroundTiers,
  updatedAt: daysAgo(6),
}

export const commerceSettings: CommerceSettings = {
  baseCurrency: 'GHS',
  foreignCurrency: 'USD',
  // Still open — README Clarifications Needed item 2. The UI says so.
  fxProvider: 'Not yet selected',
  fxRefreshMinutes: 60,
  reservationTtlMinutes: 15,
  lowStockThresholdDefault: 5,
  processingHours: 48,
  returnsWindowDays: 7,
}

/** From the PDF's "Payment Gateway Accounts" section. */
export const paymentSettings: PaymentSettings = {
  paystackEnabled: true,
  paystackBusinessName: 'Gold Coast Tokota',
  paystackSettlementCurrency: 'GHS',
  paystackMethods: ['Visa', 'Mastercard', 'Verve', 'MTN MoMo', 'Telecel Cash', 'AirtelTigo Money', 'Bank transfer'],
  stripeEnabled: true,
  stripeSettlementCurrency: 'USD',
  // Never a real key. Fixtures show the shape only.
  paystackPublicKeyMasked: 'pk_live_••••••••••••••••4f21',
  stripePublishableKeyMasked: 'pk_live_••••••••••••••••9c07',
}

export const deliverySettings: DeliverySettings = {
  domesticProvider: 'yango',
  internationalProvider: 'dhl',
  domesticEtaLabel: '1–2 business days',
  internationalBands: [
    { region: 'West Africa', eta: '5–10 business days' },
    { region: 'Europe', eta: '7–14 business days' },
    { region: 'North America', eta: '7–14 business days' },
    { region: 'Other destinations', eta: '10–21 business days' },
  ],
}

export const notificationSettings: NotificationSettings = {
  smsProvider: 'fish_africa',
  smsEnabled: true,
  emailFromName: 'Gold Coast Tokota',
  emailFromAddress: 'orders@goldcoasttokota.store',
  triggers: [
    { key: 'order_placed', label: 'Order placed', email: true, sms: true },
    { key: 'order_dispatched', label: 'Order dispatched', email: true, sms: true },
    { key: 'booking_submitted', label: 'Booking submitted', email: true, sms: true },
    { key: 'booking_confirmed', label: 'Booking confirmed', email: true, sms: true },
    { key: 'waitlist_promoted', label: 'Waitlist promotion', email: true, sms: true },
    { key: 'return_approved', label: 'Return approved', email: true, sms: false },
    { key: 'newsletter_welcome', label: 'Newsletter welcome', email: true, sms: false },
  ],
}

/**
 * All blank/false. README Feature 6 specifies WhatsApp as a deep link only —
 * the Cloud API is not integrated, and this page exists to hold the shape it
 * would take, not to imply a connection that isn't there.
 */
export const whatsappSettings: WhatsappSettings = {
  connected: false,
  phoneNumberId: '',
  wabaId: '',
  webhookUrl: 'https://api.goldcoasttokota.store/api/v1/webhooks/whatsapp',
  verifyTokenMasked: '',
  displayNumber: '+233 25 753 4297',
}

const auditActions: [string, string, string][] = [
  ['Updated site settings', 'WhatsApp number', 'Changed from +233 20 000 0000 to +233 25 753 4297'],
  ['Published blog post', 'Sip & Paint: Inside Our Saturday Workshop', 'Status pending → published'],
  ['Adjusted stock', 'GCT-1003 · Size 42', 'Available 4 → 18 (restock received)'],
  ['Extended intern access', 'Akosua Danso', 'Expiry extended by 30 days'],
  ['Issued refund', 'GCT-8100411', 'GH₵420.00 refunded to MTN MoMo'],
  ['Created workshop session', 'Sandal Sip & Paint · Saturday', 'Capacity 20'],
  ['Updated page', 'Returns & Exchanges Policy', 'Body edited'],
  ['Promoted from waitlist', 'WS-4218', 'Waitlisted → confirmed, customer notified'],
  ['Deactivated product', 'Kente Cuff Sandal', 'is_active true → false'],
  ['Updated FX settings', 'Refresh cadence', 'Hourly'],
]

export const auditEntries: AuditEntry[] = Array.from({ length: 30 }, (_, i) => {
  const [action, target, detail] = pick(auditActions)
  const actor = pick([
    ['Samuel Kumi-Gyau', 'Super Admin'],
    ['Mary Seade', 'Admin'],
    ['Isaac Boateng', 'Admin'],
    ['Isaaka Mahama', 'Staff'],
    ['Peter Nyarko', 'Staff'],
  ] as const)
  return {
    id: 9900 + i,
    actorName: actor[0],
    actorRole: actor[1],
    action,
    target,
    detail,
    at: i < 3 ? hoursAgo(i + 1) : daysAgo(int(0, 30), int(0, 23)),
  }
})
