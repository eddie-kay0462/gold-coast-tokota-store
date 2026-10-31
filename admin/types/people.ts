import type { Currency, Money, Timestamp } from './common'

/**
 * Four role tiers.
 *
 * README and the `admin_users` table enum both say two (`admin` | `staff`).
 * The brand PDF's "Admin and Staff User Roles" table names three (Super Admin /
 * Admin / Staff) against a real roster, and the business asked for a fourth:
 * `intern`, whose access is time-boxed and extendable.
 *
 * The backend enum therefore has to widen and gain an `access_expires_at`
 * column before this can be enforced server-side. Recorded in FOR_THE_TEAM.md.
 */
export type AdminRole = 'super_admin' | 'admin' | 'staff' | 'intern'

export const ADMIN_ROLE_LABELS: Record<AdminRole, string> = {
  super_admin: 'Super Admin',
  admin: 'Admin',
  staff: 'Staff',
  intern: 'Intern',
}

export interface AdminUser {
  id: number
  name: string
  email: string
  role: AdminRole
  jobTitle: string
  avatar: string | null
  /** Only ever set for `intern`. Null means access does not lapse. */
  accessExpiresAt: Timestamp | null
  /** Audit trail for extensions, newest first. */
  accessExtensions: AccessExtension[]
  lastActiveAt: Timestamp | null
  /** Invited by email and hasn't chosen a password yet, so can't sign in. */
  invitePending?: boolean
  invitedAt?: Timestamp | null
  createdAt: Timestamp
}

export interface AccessExtension {
  extendedAt: Timestamp
  extendedByName: string
  previousExpiry: Timestamp | null
  newExpiry: Timestamp
  days: number | null
}

export interface Customer {
  id: number
  /** The full name, always `firstName` + `lastName`. Shown everywhere. */
  name: string
  firstName: string
  /** Null only for some customers from before sign-up asked for it. */
  lastName: string | null
  email: string
  phone: string | null
  preferredCurrency: Currency
  /** Null for a guest who never created an account (README: guest checkout). */
  hasAccount: boolean
  /** Where their latest order went; null for someone who has never ordered. */
  country: string | null
  countryCode: string | null
  orderCount: number
  /** Money taken (paid onward), per currency: cedis always, dollars when any. */
  lifetimeValue: Money
  lifetimeValueUsd: Money | null
  lastOrderAt: Timestamp | null
  createdAt: Timestamp
}

export interface NewsletterSubscriber {
  id: number
  email: string
  source: string
  subscribedAt: Timestamp
}

export interface FeedbackEntry {
  id: number
  name: string
  email: string
  rating: number | null
  message: string
  submittedAt: Timestamp
}
