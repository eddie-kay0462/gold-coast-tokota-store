import type { Timestamp } from './common'

/**
 * WhatsApp inbox.
 *
 * SCOPE NOTE, and it matters: README Feature 6 specifies WhatsApp as a
 * *deep link only* — `https://wa.me/<number>`, no API integration. A two-way
 * inbox implies the WhatsApp Business Cloud API plus a webhook receiver, which
 * is new backend scope that does not exist and has not been costed.
 *
 * So everything below is modelled against the real Cloud API shape (message
 * direction, delivery receipts, 24-hour session window, approved templates) but
 * served entirely from fixtures, and the UI says so on every screen. Nothing
 * here sends a real message.
 */
export type MessageDirection = 'inbound' | 'outbound'

/** Mirrors the Cloud API's status webhook values. */
export type MessageStatus = 'sent' | 'delivered' | 'read' | 'failed'

export type MessageKind = 'text' | 'image' | 'document' | 'template' | 'system'

export interface ChatMessage {
  id: string
  threadId: string
  direction: MessageDirection
  kind: MessageKind
  body: string
  attachment: { filename: string; url: string; mimeType: string } | null
  status: MessageStatus
  /** True for anything this app generated locally rather than received. */
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
  sessionExpiresAt: Timestamp
  linkedOrderId: number | null
  linkedBookingId: number | null
  assignedToAdminId: number | null
  isArchived: boolean
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
