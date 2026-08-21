import type { ActivityItem, DashboardCharts, DashboardMetrics } from '~/types'
import { avatarFor, ghs, hoursAgo, iso, minsAgo, NOW, usd } from './_seed'
import { orders } from './commerce'
import { inventoryItems } from './catalogue'
import { bookings } from './bookings'
import { chatThreads } from './messaging'
import { returnRequests } from './commerce'

const ordersToday = orders.filter(
  (o) => new Date(o.placedAt).getTime() > NOW.getTime() - 864e5,
).length

const ordersThisWeek = orders.filter(
  (o) => new Date(o.placedAt).getTime() > NOW.getTime() - 7 * 864e5,
).length

const paidStatuses = ['paid', 'processing', 'shipped', 'delivered']
const sum = (cur: 'GHS' | 'USD') =>
  orders
    .filter((o) => o.currency === cur && paidStatuses.includes(o.status))
    .reduce((s, o) => s + o.total.amount, 0)

export const dashboardMetrics: DashboardMetrics = {
  ordersToday,
  ordersTodayDelta: 15.03,
  ordersThisWeek,
  revenueGhs: ghs(sum('GHS')),
  revenueUsd: usd(sum('USD')),
  revenueDelta: 11.02,
  lowStockCount: inventoryItems.filter((i) => i.quantityAvailable <= i.lowStockThreshold).length,
  pendingBookings: bookings.filter((b) => b.status === 'pending').length,
  waitlistCount: bookings.filter((b) => b.status === 'waitlisted').length,
  unreadMessages: chatThreads.reduce((s, t) => s + t.unreadCount, 0),
  openReturns: returnRequests.filter((r) => ['requested', 'approved', 'received'].includes(r.status)).length,
  // README Feature 9: metrics come from live queries and must show when read.
  generatedAt: iso(NOW),
}

const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug']
const curve = [8200, 6400, 9100, 14800, 12600, 11900, 17400, 19200]
const prior = [5100, 7300, 11200, 8900, 7400, 9800, 12100, 13000]

export const dashboardCharts: DashboardCharts = {
  revenueThisYear: months.map((label, i) => ({ label, value: curve[i]! })),
  revenueLastYear: months.map((label, i) => ({ label, value: prior[i]! })),
  ordersThisYear: months.map((label, i) => ({ label, value: Math.round(curve[i]! / 420) })),
  ordersLastYear: months.map((label, i) => ({ label, value: Math.round(prior[i]! / 420) })),
  trafficBySource: [
    { label: 'Instagram', value: 41.2 },
    { label: 'Direct', value: 22.7 },
    { label: 'Google', value: 18.4 },
    { label: 'WhatsApp', value: 9.8 },
    { label: 'Referral', value: 5.1 },
    { label: 'Other', value: 2.8 },
  ],
  trafficByDevice: [
    { label: 'Mobile', value: 27400 },
    { label: 'Desktop', value: 9100 },
    { label: 'Tablet', value: 2600 },
  ],
  trafficByLocation: [
    { label: 'Ghana', value: 46.3 },
    { label: 'United States', value: 18.9 },
    { label: 'United Kingdom', value: 14.2 },
    { label: 'Nigeria', value: 8.7 },
    { label: 'Other', value: 11.9 },
  ],
}

const activity: [ActivityItem['kind'], string, string | null, string][] = [
  ['order', 'New order GCT-8100959 — GH₵1,240.00 via MTN MoMo', 'Kofi Owusu', minsAgo(6)],
  ['message', 'Adwoa Mensah asked about a size exchange', 'Adwoa Mensah', minsAgo(18)],
  ['stock', 'Ahenema Classic — Tan, size 42 is below threshold (3 left)', null, minsAgo(44)],
  ['booking', 'Sandal Sip & Paint is at capacity — 2 now waitlisted', null, hoursAgo(2)],
  ['order', 'Order GCT-8100685 marked delivered', 'Peter Nyarko', hoursAgo(3)],
  ['content', 'Blog post “Sip & Paint: Inside Our Saturday Workshop” published', 'Mary Seade', hoursAgo(5)],
  ['customer', 'New newsletter subscriber from the checkout opt-in', null, hoursAgo(7)],
  ['booking', 'DIY-5117 custom order submitted — size 44 wide fitting', 'Yaw Boadu', hoursAgo(9)],
  ['order', 'Refund issued on GCT-8100411 — GH₵420.00', 'Isaac Boateng', hoursAgo(20)],
  ['stock', 'Restock received: Kente Panel Slide +24 across four sizes', 'Isaaka Mahama', hoursAgo(26)],
]

export const activityFeed: ActivityItem[] = activity.map(([kind, title, actor, at], i) => ({
  id: 9800 + i,
  kind,
  title,
  actor,
  avatar: actor ? avatarFor(actor) : null,
  at,
}))
