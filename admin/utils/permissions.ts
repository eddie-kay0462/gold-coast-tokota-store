import type { AdminRole } from '~/types'

/**
 * Capability-based access, not role checks scattered through templates.
 *
 * A template that asks `role === 'admin'` has to be found and edited every
 * time the role model moves — and the role model has already moved once here
 * (README says two tiers, the brand PDF names three, the business asked for a
 * fourth). Asking `can('pricing.write')` instead means adding `intern` was a
 * one-line change to the table below rather than a sweep of the codebase.
 *
 * This is a CONVENIENCE LAYER, not a security boundary. The real boundary is
 * `EnsureAdminRole` / `EnsureStaffOrAdminRole` on the Laravel side, checked
 * against the `admin` Sanctum guard. Hiding a button does not protect an
 * endpoint; it just stops staff being offered actions that will 403 on them.
 */
export const CAPABILITIES = [
  // Commerce
  'orders.view',
  'orders.update_status',
  'orders.refund',
  'returns.view',
  'returns.resolve',
  'shipments.view',
  'shipments.update',
  'customers.view',
  'customers.export',

  // Catalogue
  'products.view',
  'products.write',
  'products.delete',
  'pricing.write',
  'inventory.view',
  'inventory.adjust',

  // Bookings
  'bookings.view',
  'bookings.update_status',
  'workshops.manage',
  'waitlist.promote',

  // Content
  'content.view',
  'content.write',
  'content.publish',
  'content.delete',
  'media.upload',
  'media.delete',

  // Messaging
  'inbox.view',
  'inbox.draft',
  'inbox.reply',
  'inbox.templates',

  // Platform
  'analytics.view',
  /**
   * Split from `analytics.view` deliberately. Everyone needs the operational
   * dashboard — gating the whole landing page left Staff with a page they
   * could load but not navigate to. What Staff must not see is the money, so
   * the revenue tiles carry their own capability.
   */
  'analytics.revenue',
  'settings.view',
  'settings.write',
  'settings.payments',
  'settings.fx',
  'team.view',
  'team.manage',
  'team.extend_access',
  'audit.view',
] as const

export type Capability = (typeof CAPABILITIES)[number]

const ALL: Capability[] = [...CAPABILITIES]

/**
 * Staff: operational access only. No pricing, no refunds, no deletion, no
 * site settings — README Feature 9 is explicit about this boundary.
 */
const STAFF: Capability[] = [
  'orders.view', 'orders.update_status',
  'returns.view',
  'shipments.view', 'shipments.update',
  'customers.view',
  'products.view',
  'inventory.view', 'inventory.adjust',
  'bookings.view', 'bookings.update_status',
  'workshops.manage', 'waitlist.promote',
  'content.view', 'content.write',
  'media.upload',
  'inbox.view', 'inbox.draft', 'inbox.reply',
  'analytics.view',
  'settings.view',
  'team.view',
]

/**
 * Intern: read-only everywhere, plus the ability to draft an inbox reply for
 * someone else to send. Time-boxed via `accessExpiresAt` — see `useAuth`.
 */
const INTERN: Capability[] = [
  'orders.view',
  'returns.view',
  'shipments.view',
  'customers.view',
  'products.view',
  'inventory.view',
  'bookings.view',
  'content.view',
  'inbox.view', 'inbox.draft',
  'analytics.view',
  'team.view',
]

/**
 * Admin: everything except system-level configuration. The brand PDF draws
 * this line — "Admin ... cannot modify system-level settings or payment
 * credentials" — which is why payment keys, FX provider config and team
 * management are Super Admin only.
 */
const ADMIN: Capability[] = ALL.filter(
  (c) => !(['settings.payments', 'settings.fx', 'team.manage'] as string[]).includes(c),
)

export const ROLE_CAPABILITIES: Record<AdminRole, Capability[]> = {
  super_admin: ALL,
  admin: ADMIN,
  staff: STAFF,
  intern: INTERN,
}

/** Roles ordered from most to least privileged, for pickers and sorting. */
export const ROLE_ORDER: AdminRole[] = ['super_admin', 'admin', 'staff', 'intern']

export const ROLE_DESCRIPTIONS: Record<AdminRole, string> = {
  super_admin:
    'Full control over settings, users, products, payments, reports and integrations.',
  admin:
    'Manages products, orders, bookings, customers and content, but not system-level settings or payment credentials.',
  staff:
    'Operational access — production updates, inventory, order fulfilment and bookings. No pricing, refunds or deletions.',
  intern:
    'Read-only access for a fixed period. Can draft replies but not send them. Access lapses on the expiry date unless extended.',
}

/**
 * A human explanation for a blocked action. README Feature 9 requires staff to
 * see "a clear, non-technical error message (not a raw 403 JSON blob)", so the
 * copy is written for the person, not the log.
 */
export function denialMessage(capability: Capability, role: AdminRole): string {
  const roleLabel = { super_admin: 'Super Admin', admin: 'Admin', staff: 'Staff', intern: 'Intern' }[role]

  const specific: Partial<Record<Capability, string>> = {
    'orders.refund': 'Refunds can only be issued by an Admin.',
    'pricing.write': 'Only an Admin can change prices.',
    'products.delete': 'Only an Admin can delete products.',
    'settings.write': 'Site settings are managed by an Admin.',
    'settings.payments': 'Payment credentials are restricted to the Super Admin.',
    'settings.fx': 'Currency and exchange-rate configuration is restricted to the Super Admin.',
    'team.manage': 'Only the Super Admin can add or change team members.',
    'team.extend_access': 'Only an Admin can extend intern access.',
    'content.publish': 'Publishing is restricted — you can save a draft and ask an Admin to publish it.',
    'inbox.reply': 'You can draft a reply, but sending it needs a Staff or Admin account.',
    'analytics.revenue': 'Revenue figures are visible to Admins only.',
  }

  return specific[capability]
    ?? `Your ${roleLabel} account doesn’t have access to this. Ask an Admin if you need it.`
}
