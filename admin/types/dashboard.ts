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
  /**
   * Null from the live API until analytics (README Feature 11) collects
   * anything — "not measured", which an empty array would misstate as "no
   * traffic". See `DashboardController::charts`.
   */
  trafficBySource: SeriesPoint[] | null
  trafficByDevice: SeriesPoint[] | null
  trafficByLocation: SeriesPoint[] | null
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
