<script setup lang="ts">
import { PhArrowClockwise, PhWarningCircle } from '@phosphor-icons/vue'
import type { ActivityItem, DashboardCharts, DashboardMetrics } from '~/types'
import { formatMoneyCompact } from '~/utils/currency'

/**
 * Overview — Figma node 1:24956.
 *
 * Metric tiles, a two-series revenue chart, and the three traffic panels,
 * re-pointed at what this business actually watches: orders and revenue split
 * by currency (README Feature 9), low stock, pending bookings and the
 * waitlist.
 *
 * Revenue is gated on `analytics.revenue` rather than the page as a whole —
 * Staff run fulfilment from this screen and need it, they just should not see
 * the money.
 */
useHead({ title: 'Overview' })

const { useAdminItem } = useAdminApi()
const { formatRelative, formatNumber, formatDateTime } = useFormatters()
const { can } = useAuth()

const { item: metrics, refresh: refreshMetrics, pending } =
  useAdminItem<DashboardMetrics>('dashboard-metrics', '/admin/dashboard/metrics')
const { item: charts } = useAdminItem<DashboardCharts>('dashboard-charts', '/admin/dashboard/charts')
const { item: activity } = useAdminItem<ActivityItem[]>('dashboard-activity', '/admin/activity')

const series = ref<'revenue' | 'orders'>('revenue')
const chartData = computed(() => {
  if (!charts.value) return { primary: [], secondary: [] }
  return series.value === 'revenue'
    ? { primary: charts.value.revenueThisYear, secondary: charts.value.revenueLastYear }
    : { primary: charts.value.ordersThisYear, secondary: charts.value.ordersLastYear }
})

/** Things that need someone to act, surfaced above the fold rather than buried. */
const alerts = computed(() => {
  const m = metrics.value
  if (!m) return []
  const out: { text: string; to: string; tone: 'warning' | 'danger' }[] = []
  if (m.lowStockCount) {
    out.push({
      text: `${m.lowStockCount} variants at or below their low-stock threshold`,
      to: '/inventory', tone: 'warning',
    })
  }
  if (m.waitlistCount) {
    out.push({
      text: `${m.waitlistCount} workshop bookings waiting on a place`,
      to: '/waitlist', tone: 'warning',
    })
  }
  if (m.openReturns) {
    out.push({ text: `${m.openReturns} returns awaiting a decision`, to: '/returns', tone: 'warning' })
  }
  return out
})
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Overview" :description="`Live figures, read ${metrics ? formatRelative(metrics.generatedAt) : ''}.`">
      <template #actions>
        <UiButton variant="secondary" size="sm" :loading="pending" @click="refreshMetrics()">
          <PhArrowClockwise :size="16" />
          Refresh
        </UiButton>
      </template>
    </UiPageHeader>

    <!-- Alerts -->
    <ul v-if="alerts.length" class="flex flex-col gap-2">
      <li v-for="a in alerts" :key="a.to">
        <NuxtLink
          :to="a.to"
          class="flex items-center gap-2.5 rounded-lg border border-warning/30 bg-warning-soft px-3.5 py-2.5
                 text-ui text-warning transition-opacity hover:opacity-80"
        >
          <PhWarningCircle :size="18" class="shrink-0" />
          {{ a.text }}
        </NuxtLink>
      </li>
    </ul>

    <!-- Metric tiles.
         Always six, whatever the role: Staff cannot see revenue, so those two
         tiles are swapped for operational ones rather than removed. A constant
         count is what lets the column counts (2 / 3 / 6) always divide evenly
         instead of stranding a tile on its own row. -->
    <div v-if="metrics" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
      <UiMetricCard
        label="Orders today" :value="formatNumber(metrics.ordersToday)"
        :delta="metrics.ordersTodayDelta" :hint="`${metrics.ordersThisWeek} this week`" to="/orders"
      />

      <template v-if="can('analytics.revenue')">
        <UiMetricCard
          label="Revenue (GHS)" :value="formatMoneyCompact(metrics.revenueGhs)"
          :delta="metrics.revenueDelta" hint="Paid orders · Paystack" to="/orders"
        />
        <UiMetricCard
          label="Revenue (USD)" :value="formatMoneyCompact(metrics.revenueUsd)"
          hint="Paid orders · Stripe, rate locked" to="/orders"
        />
      </template>
      <template v-else>
        <UiMetricCard
          label="Orders this week" :value="formatNumber(metrics.ordersThisWeek)"
          hint="All currencies" to="/orders"
        />
        <UiMetricCard
          label="Open returns" :value="formatNumber(metrics.openReturns)"
          hint="Awaiting a decision" to="/returns"
        />
      </template>

      <UiMetricCard
        label="Pending bookings" :value="formatNumber(metrics.pendingBookings)"
        :hint="`${metrics.waitlistCount} waitlisted`" to="/bookings"
      />
      <UiMetricCard
        label="Unread messages" :value="formatNumber(metrics.unreadMessages)"
        hint="WhatsApp inbox" to="/inbox"
      />
      <UiMetricCard
        label="Low stock" :value="formatNumber(metrics.lowStockCount)"
        :tone="metrics.lowStockCount > 0 ? 'warning' : 'default'"
        hint="Variants at or below threshold" to="/inventory"
      />
    </div>

    <!-- Revenue + traffic source -->
    <div class="grid gap-4 xl:grid-cols-3">
      <div class="card card-pad xl:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-4">
            <button
              v-for="s in (['revenue', 'orders'] as const)" :key="s"
              type="button" class="text-section transition-colors"
              :class="series === s ? 'font-medium text-fg-strong' : 'text-fg-faint hover:text-fg-muted'"
              @click="series = s"
            >{{ s === 'revenue' ? 'Revenue' : 'Orders' }}</button>
          </div>
          <div class="flex items-center gap-4 text-meta text-fg-muted">
            <span class="flex items-center gap-1.5"><span class="size-2 rounded-pill bg-chart-1" />This year</span>
            <span class="flex items-center gap-1.5"><span class="size-2 rounded-pill bg-chart-6" />Last year</span>
          </div>
        </div>
        <div class="mt-4">
          <ChartsLine :primary="chartData.primary" :secondary="chartData.secondary" :height="260" />
        </div>
      </div>

      <div class="card card-pad">
        <h2 class="card-title">Traffic by source</h2>
        <div class="mt-4">
          <ChartsRankedBars v-if="charts" :data="charts.trafficBySource" />
        </div>
      </div>
    </div>

    <!-- Device + location -->
    <div class="grid gap-4 lg:grid-cols-2">
      <div class="card card-pad">
        <h2 class="card-title">Traffic by device</h2>
        <div class="mt-4">
          <ChartsBar v-if="charts" :data="charts.trafficByDevice" />
        </div>
      </div>
      <div class="card card-pad">
        <h2 class="card-title">Traffic by location</h2>
        <div class="mt-4">
          <ChartsDonut v-if="charts" :data="charts.trafficByLocation" />
        </div>
      </div>
    </div>

    <!-- Activity: duplicated from the right rail on purpose — the rail is
         hidden below xl, and this is the screen people leave open. -->
    <div class="card xl:hidden">
      <div class="flex items-center justify-between border-b border-border px-4 py-3 md:px-5">
        <h2 class="card-title">Recent activity</h2>
      </div>
      <ul class="divide-y divide-border">
        <li v-for="a in (activity ?? []).slice(0, 6)" :key="a.id" class="flex items-start gap-3 px-4 py-3 md:px-5">
          <UiAvatar v-if="a.actor" :name="a.actor" :src="a.avatar" :size="28" />
          <span v-else class="mt-1.5 size-1.5 shrink-0 rounded-pill bg-border-strong" />
          <span class="min-w-0">
            <span class="block text-ui text-fg">{{ a.title }}</span>
            <span class="mt-0.5 block text-meta text-fg-faint">
              {{ a.actor ? `${a.actor} · ` : '' }}{{ formatRelative(a.at) }}
            </span>
          </span>
        </li>
      </ul>
    </div>

    <p v-if="metrics" class="text-meta text-fg-faint">
      Metrics read live from the database on every load — last read {{ formatDateTime(metrics.generatedAt) }}.
    </p>
  </div>
</template>
