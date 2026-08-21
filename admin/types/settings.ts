import type { Timestamp } from './common'
import type { DiyTurnaroundTier } from './bookings'

/** README Data Models — SiteSetting, extended with what the PDF documents. */
export interface SiteSettings {
  whatsappNumber: string
  whatsappDefaultMessage: string
  /** The PDF's full "Default Greeting Message". */
  whatsappGreeting: string
  businessHours: string
  contactEmail: string
  contactPhone: string
  addressLine: string
  instagramUrl: string
  heroHeadline: string
  heroImage: string | null
  /** Replaces README's single `diy_turnaround_estimate` string. */
  diyTurnaroundTiers: DiyTurnaroundTier[]
  updatedAt: Timestamp
}

export interface CommerceSettings {
  baseCurrency: 'GHS'
  foreignCurrency: 'USD'
  fxProvider: string
  fxRefreshMinutes: number
  /** Minutes a soft reservation is held at checkout (README Feature 3). */
  reservationTtlMinutes: number
  lowStockThresholdDefault: number
  /** From the PDF's shipping policy. */
  processingHours: number
  returnsWindowDays: number
}

export interface PaymentSettings {
  paystackEnabled: boolean
  paystackBusinessName: string
  paystackSettlementCurrency: 'GHS'
  paystackMethods: string[]
  stripeEnabled: boolean
  stripeSettlementCurrency: 'USD'
  /** Never a real secret — fixtures show masked placeholders only. */
  paystackPublicKeyMasked: string
  stripePublishableKeyMasked: string
}

export interface DeliverySettings {
  /** Ghana addresses route to Yango, everything else DHL (README Feature 5). */
  domesticProvider: 'yango'
  internationalProvider: 'dhl'
  domesticEtaLabel: string
  internationalBands: { region: string; eta: string }[]
}

export interface NotificationSettings {
  smsProvider: 'fish_africa'
  smsEnabled: boolean
  emailFromName: string
  emailFromAddress: string
  triggers: { key: string; label: string; email: boolean; sms: boolean }[]
}

export interface WhatsappSettings {
  /** All false/blank until the Cloud API is actually wired up. */
  connected: boolean
  phoneNumberId: string
  wabaId: string
  webhookUrl: string
  verifyTokenMasked: string
  displayNumber: string
}

export interface AuditEntry {
  id: number
  actorName: string
  actorRole: string
  action: string
  target: string
  detail: string
  at: Timestamp
}
