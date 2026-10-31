import type { Money, Timestamp } from './common'
import type { DiyTurnaroundTier } from './bookings'

/** README Data Models: SiteSetting, extended with what the PDF documents. */
export interface SiteSettings {
  whatsappNumber: string
  whatsappDefaultMessage: string
  /** The PDF's full "Default Greeting Message". */
  whatsappGreeting: string
  businessHours: string
  /** How long a WhatsApp order holds its stock while the customer pays (1–72). */
  whatsappHoldHours?: number
  contactEmail: string
  contactPhone: string
  address: string
  instagramUrl: string
  heroHeadline: string
  /** The line under the headline; empty keeps the storefront's own. */
  heroSubheadline?: string | null
  heroImage: string | null
  /** The announcement bar's lines, up to five. Empty: the storefront's own. */
  announcements?: string[]
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
  /** Never a real secret; fixtures show masked placeholders only. */
  paystackPublicKeyMasked: string
  stripePublishableKeyMasked: string
}

export interface DeliverySettings {
  /**
   * Each zone's courier chain as configured, and what actually serves it now:
   * a courier that is switched off is skipped and the zone falls back to
   * manual dispatch.
   */
  zones: {
    zone: 'accra' | 'ghana' | 'international'
    label: string
    configured: string[]
    active: string[]
  }[]
  processingHours: number
  domesticEtaLabel: string
  internationalBands: { region: string; eta: string }[]
  quoteBufferPercent: number
  unpricedRegions: 'fallback' | 'refuse'
  fallbackRates: { accra: Money; ghana: Money; international: Money }
  opsAlerts: { email: boolean; sms: boolean }
}

export interface NotificationSettings {
  smsProvider: 'fish_africa'
  smsEnabled: boolean
  emailFromName: string
  emailFromAddress: string
  triggers: { key: string; label: string; email: boolean; sms: boolean }[]
}

export interface WhatsappSettings {
  /** True once the Cloud API credentials are set on the API's environment. */
  connected: boolean
  integration: 'cloud_api' | 'wa_me_link'
  /** Masked: last four characters only. */
  phoneNumberId: string | null
  wabaId: string | null
  /** Where Meta delivers messages and status updates. */
  webhookUrl: string | null
  /** Whether the app secret and verify token are set; never the values. */
  webhookSigningConfigured?: boolean
  webhookVerifyTokenConfigured?: boolean
  lastWebhookAt?: string | null
  graphVersion?: string
  displayNumber: string
  /** Older fixture shape. */
  verifyTokenMasked?: string
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
