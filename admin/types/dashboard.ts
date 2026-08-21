import type { Money, Timestamp } from './common'

/** Shape of GET /api/v1/admin/dashboard/metrics (README Feature 9). */
export interface DashboardMetrics {
  ordersToday: number
  ordersTodayDelta: number
  ordersThisWeek: number
  revenueGhs: Money
  revenueUsd: Money
  revenueDelta: number
  lowStockCount: number
  pendingBookings: number
  waitlistCount: number
  unreadMessages: number
  openReturns: number
  /** Feature 9 acceptance criteria: metrics must show when they were read. */
  generatedAt: Timestamp
}

export interface SeriesPoint {
  label: string
  value: number
}

export interface DashboardCharts {
  revenueThisYear: SeriesPoint[]
  revenueLastYear: SeriesPoint[]
  ordersThisYear: SeriesPoint[]
  ordersLastYear: SeriesPoint[]
  trafficBySource: SeriesPoint[]
  trafficByDevice: SeriesPoint[]
  trafficByLocation: SeriesPoint[]
}

export type ActivityKind =
  | 'order'
  | 'booking'
  | 'stock'
  | 'message'
  | 'content'
  | 'customer'

export interface ActivityItem {
  id: number
  kind: ActivityKind
  title: string
  actor: string | null
  avatar: string | null
  at: Timestamp
}
