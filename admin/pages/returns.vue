<script setup lang="ts">
import { PhProhibit, PhWarningCircle } from '@phosphor-icons/vue'
import type { ReturnRequest } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'
import { formatMoney } from '~/utils/currency'
import { humanise } from '~/utils/formatters'

/**
 * Returns & exchanges, enforcing the brand PDF's published policy:
 *   · 7-day window from delivery
 *   · custom-made and personalised items are non-returnable
 *   · refunds process in 7–14 business days to the original method
 *
 * The ineligible rows are shown rather than filtered out, with the reason
 * spelled out — someone has to reply to that customer, and they need the
 * policy line in front of them to do it.
 */
useHead({ title: 'Returns' })

const { useAdminList } = useAdminApi()
const { formatDate, formatRelative, daysUntil } = useFormatters()
const { items: returns, pending } = useAdminList<ReturnRequest>('admin-returns', '/admin/returns')

const r = useResource<ReturnRequest>(returns, {
  searchFields: ['orderReference', 'customerName', 'itemsSummary'],
  defaultSort: 'requestedAt',
  defaultDir: 'desc',
})

const statusOptions = [
  { value: 'all', label: 'All statuses' },
  ...['requested', 'approved', 'rejected', 'received', 'refunded', 'exchanged']
    .map((s) => ({ value: s, label: humanise(s) })),
]

const columns: Column<ReturnRequest>[] = [
  { key: 'orderReference', label: 'Order', sortable: true },
  { key: 'customerName', label: 'Customer', sortable: true },
  { key: 'reason', label: 'Reason' },
  { key: 'windowClosesAt', label: 'Window', sortable: true },
  { key: 'refundAmount', label: 'Refund', align: 'right' },
  { key: 'status', label: 'Status', sortable: true },
]

const windowLabel = (iso: string) => {
  const d = daysUntil(iso)
  if (d === null) return '—'
  return d < 0 ? `Closed ${formatRelative(iso)}` : `${d} day${d === 1 ? '' : 's'} left`
}
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader
      title="Returns & exchanges"
      description="Returns are accepted within 7 days of delivery. Custom-made and personalised items are non-returnable."
    />

    <UiToolbar
      v-model:search="r.search.value" :active-filter-count="r.activeFilterCount.value"
      placeholder="Search by order, customer or item…" @clear-filters="r.clearFilters()"
    >
      <template #actions>
        <UiSelect
          :model-value="r.filters.value.status ?? 'all'" :options="statusOptions" class="w-44"
          @update:model-value="r.setFilter('status', $event)"
        />
      </template>
    </UiToolbar>

    <UiDataTable
      :columns="columns" :rows="r.paged.value" :loading="pending"
      :sort="r.sort.value" :dir="r.dir.value"
      empty-title="No returns match"
      empty-description="Nothing is currently awaiting a decision under this filter."
      @sort="r.toggleSort"
    >
      <template #cell-orderReference="{ row }">
        <span class="font-mono text-fg-strong">{{ row.orderReference }}</span>
        <span class="block truncate text-meta text-fg-faint">{{ row.itemsSummary }}</span>
      </template>
      <template #cell-reason="{ row }">
        <span class="text-fg">{{ humanise(row.reason) }}</span>
        <span
          v-if="!row.isEligible"
          class="mt-1 flex items-start gap-1.5 text-meta text-danger"
        >
          <PhProhibit :size="13" class="mt-px shrink-0" />
          {{ row.ineligibleReason }}
        </span>
      </template>
      <template #cell-windowClosesAt="{ row }">
        <span :class="(daysUntil(row.windowClosesAt) ?? 0) < 0 ? 'text-danger' : 'text-fg-muted'">
          {{ windowLabel(row.windowClosesAt) }}
        </span>
        <span class="block text-meta text-fg-faint">Closes {{ formatDate(row.windowClosesAt) }}</span>
      </template>
      <template #cell-refundAmount="{ row }">
        <span v-if="row.refundAmount" class="text-fg-strong">{{ formatMoney(row.refundAmount) }}</span>
        <span v-else class="text-meta text-fg-faint">Exchange</span>
      </template>
      <template #cell-status="{ row }">
        <UiStatusBadge :status="row.status" />
      </template>
      <template #actions="{ row }">
        <UiPermissionGate capability="returns.resolve" quiet>
          <UiButton v-if="row.status === 'requested'" variant="ghost" size="sm">Resolve</UiButton>
        </UiPermissionGate>
      </template>
      <template #footer>
        <UiPagination
          :page="r.page.value" :page-count="r.pageCount.value"
          :total="r.total.value" :per-page="r.perPage.value"
          @update:page="r.page.value = $event"
        />
      </template>
    </UiDataTable>

    <p class="flex items-start gap-2 text-meta text-fg-faint">
      <PhWarningCircle :size="14" class="mt-px shrink-0" />
      Approved refunds are processed within 7–14 business days using the original payment
      method. Shipping charges are non-refundable unless the error was ours.
    </p>
  </div>
</template>
