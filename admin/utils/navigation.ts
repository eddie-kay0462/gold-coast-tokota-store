import type { Capability } from '~/utils/permissions'

/**
 * The sidebar's single source of truth.
 *
 * Grouped as the Figma kit groups it (a labelled section, items, optional
 * sub-items), but named for this business rather than the kit's CRM demo:
 * "Leads → Deals / Follow-Ups / Customers" becomes "Orders → All orders /
 * Fulfilment / Returns / Shipments".
 *
 * Every entry carries the capability it needs. An item the viewer cannot use
 * is not rendered at all — showing a Staff account a Payments link that will
 * 403 is worse than not showing it.
 */
export interface NavItem {
  label: string
  to?: string
  /** Phosphor icon component name, resolved by SidebarNav. */
  icon: string
  capability?: Capability
  children?: NavChild[]
  /** Key into the badge map supplied by the layout (unread counts etc.). */
  badgeKey?: string
}

export interface NavChild {
  label: string
  to: string
  capability?: Capability
}

export interface NavGroup {
  label: string
  items: NavItem[]
}

export const NAVIGATION: NavGroup[] = [
  {
    label: 'Operations',
    items: [
      { label: 'Overview', to: '/', icon: 'PhChartPieSlice', capability: 'analytics.view' },
      { label: 'Analytics', to: '/analytics', icon: 'PhChartLineUp', capability: 'analytics.view' },
      {
        label: 'Orders',
        icon: 'PhReceipt',
        capability: 'orders.view',
        badgeKey: 'openOrders',
        children: [
          { label: 'All orders', to: '/orders', capability: 'orders.view' },
          { label: 'Fulfilment', to: '/orders/board', capability: 'orders.view' },
          { label: 'Returns', to: '/returns', capability: 'returns.view' },
          { label: 'Shipments', to: '/shipments', capability: 'shipments.view' },
        ],
      },
      {
        label: 'Catalogue',
        icon: 'PhPackage',
        capability: 'products.view',
        children: [
          { label: 'Products', to: '/products', capability: 'products.view' },
          { label: 'Inventory', to: '/inventory', capability: 'inventory.view' },
          { label: 'Categories', to: '/categories', capability: 'products.view' },
        ],
      },
      {
        label: 'Bookings',
        icon: 'PhCalendarDots',
        capability: 'bookings.view',
        badgeKey: 'pendingBookings',
        children: [
          { label: 'All bookings', to: '/bookings', capability: 'bookings.view' },
          { label: 'Calendar', to: '/bookings/calendar', capability: 'bookings.view' },
          { label: 'Workshops', to: '/workshops', capability: 'bookings.view' },
          { label: 'Waitlist', to: '/waitlist', capability: 'bookings.view' },
        ],
      },
    ],
  },
  {
    label: 'Relationships',
    items: [
      { label: 'Inbox', to: '/inbox', icon: 'PhChatsTeardrop', capability: 'inbox.view', badgeKey: 'unreadMessages' },
      { label: 'Customers', to: '/customers', icon: 'PhIdentificationBadge', capability: 'customers.view' },
      { label: 'Team', to: '/team', icon: 'PhUsersThree', capability: 'team.view' },
      {
        label: 'Audience',
        icon: 'PhMegaphone',
        capability: 'customers.view',
        children: [
          { label: 'Newsletter', to: '/newsletter', capability: 'customers.view' },
          { label: 'Feedback', to: '/feedback', capability: 'customers.view' },
        ],
      },
    ],
  },
  {
    label: 'Content',
    items: [
      { label: 'Blog', to: '/blog', icon: 'PhNotebook', capability: 'content.view' },
      { label: 'Pages', to: '/pages', icon: 'PhFolderNotch', capability: 'content.view' },
      { label: 'Media', to: '/media', icon: 'PhImagesSquare', capability: 'content.view' },
    ],
  },
  {
    label: 'Configuration',
    items: [
      {
        label: 'Settings',
        icon: 'PhGear',
        capability: 'settings.view',
        children: [
          { label: 'General', to: '/settings', capability: 'settings.view' },
          { label: 'Store & currency', to: '/settings/store', capability: 'settings.view' },
          { label: 'WhatsApp', to: '/settings/whatsapp', capability: 'settings.view' },
          { label: 'Payments', to: '/settings/payments', capability: 'settings.payments' },
          { label: 'Delivery', to: '/settings/delivery', capability: 'settings.view' },
          { label: 'Notifications', to: '/settings/notifications', capability: 'settings.view' },
          { label: 'SEO', to: '/settings/seo', capability: 'settings.view' },
        ],
      },
      { label: 'Roles & access', to: '/settings/roles', icon: 'PhIdentificationCard', capability: 'team.view' },
      { label: 'Audit log', to: '/settings/audit', icon: 'PhClockCounterClockwise', capability: 'audit.view' },
    ],
  },
]

/** Breadcrumb trail for a path, derived from NAVIGATION rather than duplicated. */
export function breadcrumbFor(path: string): { label: string; to?: string }[] {
  if (path === '/') return [{ label: 'Overview' }]

  for (const group of NAVIGATION) {
    for (const item of group.items) {
      if (item.to === path) return [{ label: group.label }, { label: item.label }]
      const child = item.children?.find((c) => c.to === path)
      if (child) return [{ label: item.label, to: item.children![0]!.to }, { label: child.label }]
    }
  }

  // Detail routes (/orders/3007, /blog/8001) — walk up to the closest parent.
  const parent = path.slice(0, path.lastIndexOf('/')) || '/'
  if (parent !== path) {
    const trail = breadcrumbFor(parent)
    if (trail.length) {
      const last = path.slice(path.lastIndexOf('/') + 1)
      return [...trail.slice(0, -1), { ...trail[trail.length - 1]!, to: parent }, { label: last === 'new' ? 'New' : `#${last}` }]
    }
  }

  return [{ label: 'Dashboard' }]
}
