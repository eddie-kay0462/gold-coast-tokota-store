import type { Timestamp } from './common'

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
