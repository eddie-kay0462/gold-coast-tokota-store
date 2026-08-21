<script setup lang="ts">
import { PhDownloadSimple, PhWarningCircle } from '@phosphor-icons/vue'
import type { DashboardCharts, DashboardMetrics, Order } from '~/types'
import type { DateRange } from '~/components/ui/DateRangePicker.vue'
import { formatMoney, formatMoneyCompact } from '~/utils/currency'

/**
 * Analytics.
 *
 * Deliberately built on the server-side order data rather than GA4. README
 * Feature 11 is explicit that dashboard figures must stay accurate when a
 * customer blocks the tracking script — so revenue, order counts and the
 * currency split are computed from orders, and only the traffic panels come
 * from the analytics feed. The distinction is labelled on the page, because an
 * operator needs to know which numbers survive an ad-blocker.
 */
useHead({ title: 'Analytics' })

const { useAdminList, useAdminItem } = useAdminApi()
const { formatDate, formatNumber, now } = useFormatters()

const { items: orders, pending } = useAdminList<Order>('analytics-orders', '/admin/orders')
const { item: charts } = useAdminItem<DashboardCharts>('analytics-charts', '/admin/dashboard/charts')
const { item: metrics } = useAdminItem<DashboardMetrics>('analytics-metrics', '/admin/dashboard/metrics')

const today = computed(() => now.value.toISOString().slice(0, 10))
const range = ref<DateRange>({ from: '', to: '', label: 'Last 30 days' })
watchEffect(() => {
  if (range.value.from) return
  const from = new Date(now.value.getTime() - 29 * 864e5).toISOString().slice(0, 10)
  range.value = { from, to: today.value, label: 'Last 30 days' }
})

const inRange = computed(() =>
  orders.value.filter((o) => {
    const d = o.placedAt.slice(0, 10)
    return d >= range.value.from && d <= range.value.to
  }),
)

const paid = computed(() =>
  inRange.value.filter((o) => ['paid', 'processing', 'shipped', 'delivered'].includes(o.status)),
)

const sum = (cur: 'GHS' | 'USD') =>
  paid.value.filter((o) => o.currency === cur).reduce((s, o) => s + o.total.amount, 0)

const stats = computed(() => {
  const ghs = sum('GHS')
  const usd = sum('USD')
  const count = paid.value.length
  return {
    revenueGhs: { amount: ghs, currency: 'GHS' as const },
    revenueUsd: { amount: usd, currency: 'USD' as const },
    orders: count,
    // Averaged within GHS only — mixing currencies would produce a number
    // that does not mean anything.
    aov: {
      amount: count ? Math.round(ghs / Math.max(1, paid.value.filter((o) => o.currency === 'GHS').length)) : 0,
      currency: 'GHS' as const,
    },
    conversionNote: inRange.value.length - count,
  }
})

/** Which products actually sold in the window, by units. */
const topProducts = computed(() => {
  const tally = new Map<string, { name: string; units: number; revenue: number; currency: 'GHS' | 'USD' }>()
  for (const o of paid.value) {
    for (const i of o.items) {
      const key = i.productName
      const row = tally.get(key) ?? { name: key, units: 0, revenue: 0, currency: 'GHS' as const }
      row.units += i.quantity
      if (o.currency === 'GHS') row.revenue += i.unitPrice.amount * i.quantity
      tally.set(key, row)
    }
  }
  return [...tally.values()].sort((a, b) => b.units - a.units).slice(0, 8)
})

const byCountry = computed(() => {
  const tally = new Map<string, number>()
  for (const o of paid.value) {
    tally.set(o.shippingAddress.country, (tally.get(o.shippingAddress.country) ?? 0) + 1)
  }
  return [...tally.entries()]
    .map(([label, value]) => ({ label, value }))
    .sort((a, b) => b.value - a.value)
    .slice(0, 6)
})

const byMethod = computed(() => {
  const tally = new Map<string, number>()
  for (const o of paid.value) {
    const label = o.paymentGateway === 'paystack' ? 'Paystack (GHS)' : 'Stripe (USD)'
    tally.set(label, (tally.get(label) ?? 0) + 1)
  }
  return [...tally.entries()].map(([label, value]) => ({ label, value }))
})
</script>

<template>
  <div class="admin-stack">
    <UiPermissionGate capability="analytics.view">
      <UiPageHeader
        title="Analytics"
        description="Sales computed from order records, so the figures hold even when a customer blocks tracking."
      >
        <template #actions>
          <UiButton variant="secondary" size="sm">
            <PhDownloadSimple :size="16" />
            Export
          </UiButton>
        </template>
      </UiPageHeader>

      <UiDateRangePicker v-model="range" :today="today" />

      <p class="text-meta text-fg-faint">
        {{ formatDate(range.from) }} – {{ formatDate(range.to) }} ·
        {{ formatNumber(inRange.length) }} orders placed, {{ formatNumber(paid.length) }} paid
      </p>

      <UiPermissionGate capability="analytics.revenue">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <UiMetricCard label="Revenue (GHS)" :value="formatMoneyCompact(stats.revenueGhs)" hint="Paystack" />
          <UiMetricCard label="Revenue (USD)" :value="formatMoneyCompact(stats.revenueUsd)" hint="Stripe · rate locked per order" />
          <UiMetricCard label="Paid orders" :value="formatNumber(stats.orders)" :hint="`${stats.conversionNote} not paid`" />
          <UiMetricCard label="Average order (GHS)" :value="formatMoney(stats.aov)" hint="Cedi orders only" />
        </div>
        <template #denied>
          <div class="card flex items-start gap-3 px-4 py-3.5">
            <PhWarningCircle :size="18" class="mt-px shrink-0 text-fg-faint" />
            <p class="text-ui text-fg-muted">
              Revenue figures are visible to Admins only. Everything below is available to you.
            </p>
          </div>
        </template>
      </UiPermissionGate>

      <div class="grid gap-4 xl:grid-cols-3">
        <section class="card card-pad xl:col-span-2">
          <h2 class="card-title">Orders over the year</h2>
          <p class="mt-1 text-meta text-fg-faint">From order records, not the tracking script.</p>
          <div class="mt-4">
            <ChartsLine
              v-if="charts" :primary="charts.ordersThisYear" :secondary="charts.ordersLastYear" :height="240"
            />
          </div>
        </section>

        <section class="card card-pad">
          <h2 class="card-title">Where orders ship</h2>
          <div class="mt-4">
            <ChartsDonut :data="byCountry" unit="" />
          </div>
        </section>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <section class="card">
          <div class="border-b border-border p-4 md:p-5">
            <h2 class="card-title">Best sellers</h2>
            <p class="mt-1 text-meta text-fg-faint">By units, within the selected range.</p>
          </div>
          <UiEmptyState v-if="!topProducts.length" title="No sales in this range" />
          <ul v-else class="divide-y divide-border">
            <li v-for="p in topProducts" :key="p.name" class="flex items-center gap-3 px-4 py-2.5 md:px-5">
              <span class="min-w-0 flex-1 truncate text-ui text-fg">{{ p.name }}</span>
              <span class="shrink-0 text-ui text-fg-strong">{{ p.units }}</span>
              <span class="w-8 shrink-0 text-right text-meta text-fg-faint">units</span>
            </li>
          </ul>
        </section>

        <div class="flex flex-col gap-4">
          <section class="card card-pad">
            <h2 class="card-title">Gateway split</h2>
            <div class="mt-4">
              <ChartsRankedBars :data="byMethod" unit="" />
            </div>
          </section>

          <section class="card card-pad">
            <div class="flex items-baseline justify-between gap-2">
              <h2 class="card-title">Traffic by source</h2>
              <UiBadge tone="outline" size="sm">GA4</UiBadge>
            </div>
            <p class="mt-1 text-meta text-fg-faint">
              Client-side tracking — undercounts anyone using an ad-blocker.
            </p>
            <div class="mt-4">
              <ChartsRankedBars v-if="charts" :data="charts.trafficBySource" />
            </div>
          </section>
        </div>
      </div>

      <p v-if="metrics" class="text-meta text-fg-faint">
        Order data read live on every load. Traffic figures come from Google Analytics and are
        mirrored server-side for the metrics above, so a blocked script never affects them.
      </p>
    </UiPermissionGate>
  </div>
</template>
