import type { Money, Timestamp } from './common'

/**
 * WhatsApp inbox, served by `/admin/inbox/*` on the Laravel API (WhatsApp
 * Cloud API, with coexistence: the brand phone's WhatsApp Business app keeps
 * working and its replies arrive here as `source: 'phone_app'`).
 *
 * With no Cloud API credentials the API uses a logging stand-in: everything
 * works, nothing is delivered, and each such message has `simulated: true`.
 * With `NUXT_PUBLIC_ADMIN_DATA=fixtures` the page runs on sample data.
 */
export type MessageDirection = 'inbound' | 'outbound'

/**
 * The Cloud API's status webhook values, plus three of the inbox's own:
 * `draft` (an intern's reply awaiting someone who can send), `queued`
 * (accepted, not yet handed to WhatsApp) and `received` (inbound).
 */
export type MessageStatus = 'draft' | 'queued' | 'sent' | 'delivered' | 'read' | 'failed' | 'received'

export type MessageKind =
  | 'text' | 'image' | 'document' | 'audio' | 'video' | 'sticker' | 'location'
  | 'template' | 'interactive' | 'system'

/** Who or what produced the message. */
export type MessageSource = 'customer' | 'agent' | 'automation' | 'phone_app' | 'history'

export interface ChatMessage {
  id: string
  threadId: string
  direction: MessageDirection
  kind: MessageKind
  body: string
  /** `url` is null while a customer's file is still being fetched from WhatsApp. */
  attachment: { filename: string; url: string | null; mimeType: string } | null
  status: MessageStatus
  /** Why WhatsApp refused it, when `status` is `failed`. */
  error?: string | null
  source?: MessageSource
  templateName?: string | null
  /** A link button (e.g. a payment link) sent with the message. */
  cta?: { button: string; url: string } | null
  sentByName?: string | null
  draftedByName?: string | null
  /** True when the logging stand-in "sent" it, so nothing reached WhatsApp. */
  simulated: boolean
  sentAt: Timestamp
}

export type ThreadTopic =
  | 'order'
  | 'booking'
  | 'diy_order'
  | 'returns'
  | 'wholesale'
  | 'general'

export interface ChatThread {
  id: string
  contactName: string
  contactPhone: string
  avatar: string | null
  topic: ThreadTopic
  unreadCount: number
  isOnline: boolean
  lastSeenAt: Timestamp | null
  lastMessagePreview: string
  lastMessageAt: Timestamp
  /**
   * The Cloud API only permits free-form replies within 24 hours of the
   * customer's last message; outside it you must send an approved template.
   * Surfacing this is the difference between a toy and something an operator
   * can trust.
   */
  /** Null when the customer has never written: only a template can open the chat. */
  sessionExpiresAt: Timestamp | null
  linkedOrderId: number | null
  linkedOrderReference?: string | null
  linkedBookingId: number | null
  assignedToAdminId: number | null
  assignedToName?: string | null
  isArchived: boolean
  status?: 'open' | 'closed'
  customerId?: number | null
  /** The customer replied STOP: no templates or automatic messages. */
  optedOut?: boolean
  /** Arrived with a cart from the storefront that isn't an order yet. */
  hasOrderDraft?: boolean
}

export type TemplateStatus = 'approved' | 'pending' | 'rejected'

export interface MessageTemplate {
  id: string
  name: string
  category: 'utility' | 'marketing' | 'authentication'
  language: string
  status: TemplateStatus
  body: string
  variables: string[]
  rejectionReason: string | null
  updatedAt: Timestamp
}

// --- context panel: `/admin/inbox/threads/{id}/context` and `/admin/inbox/lookup/*` ---

export interface InboxContext {
  contact: { name: string; phone: string; profileName: string | null; optedOut: boolean; firstContactAt: Timestamp }
  customer: {
    id: number; name: string; email: string; phone: string | null
    preferredCurrency: 'GHS' | 'USD' | null; since: Timestamp
  } | null
  orders: {
    reference: string; status: string; channel: 'web' | 'whatsapp'; total: Money; items: string[]
    shipmentStatus: string | null; trackingReference: string | null; trackingUrl: string | null
    placedAt: Timestamp; isLinked: boolean
  }[]
  bookings: {
    id: number; type: 'workshop' | 'diy_order'; status: string; experience: string | null
    date: string | null; slot: string | null; attendees: number | null; createdAt: Timestamp
  }[]
}

export interface StockVariant {
  inventoryItemId: number; size: string | null; colour: string | null; label: string
  /** On the shelf minus held for orders awaiting payment: what can be sold now. */
  sellable: number; onShelf: number; held: number; low: boolean
}

export interface StockLookupProduct {
  id: number; name: string; slug: string; url: string; sku: string; isActive: boolean
  image: string | null; price: Money; priceUsd: Money | null; compareAt: Money | null
  sellableTotal: number; variants: StockVariant[]
}

export interface EventsLookup {
  sessions: {
    id: number; experience: string | null; duration: string | null; date: string; slot: string
    capacity: number; remaining: number; waitlisted: number; locationNotes: string | null
  }[]
  byAppointment: { name: string; days: string | null; duration: string | null; capacity: number }[]
  posts: { title: string; url: string; publishedAt: Timestamp }[]
}

export interface PolicyLine { label: string; text: string; section: string }

export interface InfoLookup {
  businessHours: string | null
  delivery: PolicyLine[]
  returns: PolicyLine[]
  paymentMethods: string[]
  turnaround: { label: string; estimate: string }[]
  rates: { accra: Money; regions: { region: string; rate: Money }[]; internationalNote: string }
}

// --- Order on WhatsApp: `/admin/inbox/threads/{id}/order-builder` and friends ---

export interface OrderLineDraft {
  inventoryItemId: number
  quantity: number
  productName: string | null
  variantLabel: string | null
  unitPrice: Money | null
  unitPriceUsd?: Money | null
  sellable: number
  /** Why this line can't go into an order as it stands, if anything. */
  problem?: string | null
}

export interface WhatsAppOrderSummary {
  reference: string
  status: string
  total: Money
  heldUntil: Timestamp | null
  paymentReference: string | null
  placedAt: Timestamp
}

export interface OrderBuilderData {
  draft: { reference: string; currency: 'GHS' | 'USD'; createdAt: Timestamp; lines: OrderLineDraft[] } | null
  prefill: {
    fullName: string | null; email: string | null; phone: string
    line1: string | null; city: string | null; region: string | null; postcode: string | null; country: string
    fromLastOrder: string | null
    /** The newest location the customer shared in the chat: the delivery pin. */
    location: { lat: number; lng: number; label: string | null; sharedAt: Timestamp } | null
  }
  currency: 'GHS' | 'USD'
  regions: string[]
  holdHours: number
  windowOpen: boolean
  paymentTemplateReady: boolean
  openOrders: WhatsAppOrderSummary[]
}

export interface ShippingQuoteOption {
  id: string; carrier: string; label: string; amountGhs: number; free: boolean; eta: string | null
}

export interface PlacedWhatsAppOrder {
  order: WhatsAppOrderSummary
  paymentUrl: string | null
  message: ChatMessage | null
  /** Set when the order stands but its link could not be sent by itself. */
  warning: string | null
}

// --- semi-automation: `/admin/inbox/quick-replies` and `/admin/inbox/automation` ---

export interface QuickReply {
  id: number
  title: string
  /** Typed after "/" in the reply box. */
  shortcut: string | null
  /** With {slots}, filled by the server for each chat. */
  body: string
  sortOrder: number
}

export interface BusinessHoursValue { days: number[]; open: string; close: string }

export interface InboxAutomation {
  autoGreeting: boolean
  greeting: string | null
  afterHoursEnabled: boolean
  afterHoursMessage: string | null
  /** What goes out when the message above is blank. */
  afterHoursPreview: string
  hours: BusinessHoursValue
  hoursLabel: string
  openNow: boolean
  slots: { slot: string; description: string }[]
}
