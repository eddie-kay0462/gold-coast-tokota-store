import type { Timestamp } from './common'

export type BookingType = 'workshop' | 'diy_order'

export type BookingStatus =
  | 'pending'
  | 'confirmed'
  | 'waitlisted'
  | 'completed'
  | 'cancelled'

/**
 * The workshop catalogue, from the brand PDF's "Workshop Schedule" table.
 *
 * README models only a flat `WorkshopSession` (date, slot, capacity). In
 * reality the business runs six named experiences, each with its own recurring
 * day pattern, slot, duration and capacity — a session is an *instance* of one
 * of these. Modelling the type separately is what lets the owner schedule
 * "another Sandal Sip & Paint" without re-entering capacity every time.
 */
export interface WorkshopType {
  id: number
  name: string
  slug: string
  /** Human-readable recurrence, e.g. "Every Saturday", "By appointment". */
  daysLabel: string
  slotLabel: string
  durationLabel: string
  capacity: number
  description: string
  isActive: boolean
}

export interface WorkshopSession {
  id: number
  workshopTypeId: number
  workshopTypeName: string
  scheduledDate: string          // YYYY-MM-DD
  scheduledSlot: string          // "10:00 AM – 1:00 PM"
  capacity: number
  confirmedCount: number
  waitlistCount: number
  locationNotes: string
  createdByAdminId: number
  createdAt: Timestamp
}

export interface Booking {
  id: number
  reference: string
  type: BookingType
  status: BookingStatus
  customerName: string
  customerEmail: string
  customerPhone: string
  /** Workshop bookings only. */
  workshopSessionId: number | null
  workshopTypeName: string | null
  attendeeCount: number | null
  /** DIY orders only — no capacity or date constraint (README Feature 7). */
  scheduledDate: string | null
  diySpecs: DiySpecs | null
  /** Position in the queue for a waitlisted workshop booking. 1 is next up. */
  waitlistPosition: number | null
  whatsappThreadId: string | null
  notes: string
  createdAt: Timestamp
}

export interface DiySpecs {
  tierId: string
  size: string
  footLengthCm: number | null
  colourway: string
  soleMaterial: string
  referenceImages: string[]
  preferredFulfilment: 'pickup' | 'delivery'
  measurementsNote: string
}

/**
 * DIY turnaround, from the PDF's "DIY Sandal Order Turnaround Time" table.
 *
 * README's SiteSetting carries a single `diy_turnaround_estimate` string. The
 * business quotes five different windows depending on order type, so this is a
 * matrix the owner edits — the storefront shows the row matching what the
 * customer is ordering, not one blanket number.
 */
export interface DiyTurnaroundTier {
  id: string
  label: string
  estimate: string
  sortOrder: number
}
