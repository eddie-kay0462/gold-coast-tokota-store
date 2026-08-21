import type { ListQuery, Paginated } from '~/types'

import { adminUsers, customers, feedbackEntries, newsletterSubscribers } from './people'
import { categories, fxRate, inventoryItems, products } from './catalogue'
import { orders, returnRequests, shipments } from './commerce'
import { bookings, diyTurnaroundTiers, workshopSessions, workshopTypes } from './bookings'
import { blogPosts, cmsPages, mediaAssets } from './content'
import { chatMessages, chatThreads, messageTemplates } from './messaging'
import {
  auditEntries, commerceSettings, deliverySettings, notificationSettings,
  paymentSettings, siteSettings, whatsappSettings,
} from './settings'
import { activityFeed, dashboardCharts, dashboardMetrics } from './dashboard'

export * from './_seed'
export {
  adminUsers, customers, feedbackEntries, newsletterSubscribers,
  categories, fxRate, inventoryItems, products,
  orders, returnRequests, shipments,
  bookings, diyTurnaroundTiers, workshopSessions, workshopTypes,
  blogPosts, cmsPages, mediaAssets,
  chatMessages, chatThreads, messageTemplates,
  auditEntries, commerceSettings, deliverySettings, notificationSettings,
  paymentSettings, siteSettings, whatsappSettings,
  activityFeed, dashboardCharts, dashboardMetrics,
}

/**
 * Fixture routing table.
 *
 * Keys are API paths WITHOUT the `/api/v1` prefix, matching what call sites
 * pass to `adminFetch`. A trailing `/:id` segment is handled by the matcher
 * below rather than being enumerated here.
 */
const collections: Record<string, unknown[]> = {
  '/admin/orders': orders,
  '/admin/returns': returnRequests,
  '/admin/shipments': shipments,
  '/admin/products': products,
  '/admin/categories': categories,
  '/admin/inventory': inventoryItems,
  '/admin/customers': customers,
  '/admin/bookings': bookings,
  '/admin/workshop-types': workshopTypes,
  '/admin/workshop-sessions': workshopSessions,
  '/admin/blog': blogPosts,
  '/admin/pages': cmsPages,
  '/admin/media': mediaAssets,
  '/admin/newsletter': newsletterSubscribers,
  '/admin/feedback': feedbackEntries,
  '/admin/team': adminUsers,
  '/admin/audit': auditEntries,
  '/admin/inbox/threads': chatThreads,
  '/admin/inbox/messages': chatMessages,
  '/admin/inbox/templates': messageTemplates,
  '/admin/activity': activityFeed,
}

const singletons: Record<string, unknown> = {
  '/admin/dashboard/metrics': dashboardMetrics,
  '/admin/dashboard/charts': dashboardCharts,
  '/site-settings': siteSettings,
  '/admin/site-settings': siteSettings,
  '/admin/settings/commerce': commerceSettings,
  '/admin/settings/payments': paymentSettings,
  '/admin/settings/delivery': deliverySettings,
  '/admin/settings/notifications': notificationSettings,
  '/admin/settings/whatsapp': whatsappSettings,
  '/admin/settings/diy-turnaround': diyTurnaroundTiers,
  '/fx-rate': fxRate,
}

/** Case-insensitive substring match across every string field on a record. */
function matchesSearch(row: unknown, term: string): boolean {
  if (!term) return true
  const needle = term.toLowerCase()
  return Object.values(row as Record<string, unknown>).some((v) => {
    if (typeof v === 'string') return v.toLowerCase().includes(needle)
    if (typeof v === 'number') return String(v).includes(needle)
    return false
  })
}

function applyQuery<T>(rows: T[], q: ListQuery): Paginated<T> {
  let out = [...rows]

  if (q.search) out = out.filter((r) => matchesSearch(r, String(q.search)))

  // Any extra key on the query is treated as an equality filter on that field.
  // `all` and empty values are ignored so a "no filter" select works.
  const reserved = new Set(['page', 'perPage', 'search', 'sort', 'dir'])
  for (const [key, value] of Object.entries(q)) {
    if (reserved.has(key) || value == null || value === '' || value === 'all') continue
    out = out.filter((r) => String((r as Record<string, unknown>)[key]) === String(value))
  }

  if (q.sort) {
    const dir = q.dir === 'desc' ? -1 : 1
    out.sort((a, b) => {
      const av = (a as Record<string, unknown>)[q.sort!]
      const bv = (b as Record<string, unknown>)[q.sort!]
      if (av == null) return 1
      if (bv == null) return -1
      if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir
      return String(av).localeCompare(String(bv)) * dir
    })
  }

  const total = out.length
  const page = q.page ?? 1
  const perPage = q.perPage ?? total
  const start = (page - 1) * perPage
  return { items: out.slice(start, start + perPage), total, page, perPage }
}

export interface FixtureResult {
  found: boolean
  data?: unknown
  meta?: Record<string, unknown>
}

/**
 * Resolve an API path against the fixture set.
 *
 * Handles three shapes:
 *   /admin/orders          → paginated list, honouring search/sort/filter
 *   /admin/orders/3007     → the single record with that id
 *   /admin/dashboard/metrics → a singleton object
 */
export function resolveFixture(path: string, query: ListQuery = {}): FixtureResult {
  const clean = path.split('?')[0]!.replace(/\/$/, '')

  if (clean in singletons) return { found: true, data: singletons[clean] }
  if (clean in collections) {
    const page = applyQuery(collections[clean]!, query)
    return {
      found: true,
      data: page.items,
      meta: { total: page.total, page: page.page, perPage: page.perPage },
    }
  }

  // Detail route: strip the last segment and look for the parent collection.
  const lastSlash = clean.lastIndexOf('/')
  const parent = clean.slice(0, lastSlash)
  const key = clean.slice(lastSlash + 1)
  if (parent in collections) {
    const row = collections[parent]!.find((r) => {
      const rec = r as Record<string, unknown>
      return String(rec.id) === key || String(rec.slug) === key
    })
    return row ? { found: true, data: row } : { found: false }
  }

  return { found: false }
}
