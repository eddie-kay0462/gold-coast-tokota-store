<script setup lang="ts">
import type { Booking } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'
import { humanise } from '~/utils/formatters'

/**
 * Bookings, split by the two types README Feature 7 defines. They are not
 * variations of one thing: workshops are capacity-limited with a waitlist,
 * DIY orders are an unlimited queue that is never blocked or rejected. Tabs
 * keep that distinction visible rather than burying it in a filter.
 */
useHead({ title: 'Bookings' })

const { useAdminList } = useAdminApi()
const { formatDate, formatRelative } = useFormatters()
const { items: bookings, pending } = useAdminList<Booking>('admin-bookings', '/admin/bookings')

const tab = ref<'workshop' | 'diy_order'>('workshop')
const visible = computed(() => bookings.value.filter((b) => b.type === tab.value))

const r = useResource<Booking>(visible, {
  searchFields: ['reference', 'customerName', 'customerEmail'],
  defaultSort: 'createdAt',
  defaultDir: 'desc',
})

const statusOptions = [
  { value: 'all', label: 'All statuses' },
  ...['pending', 'confirmed', 'waitlisted', 'completed', 'cancelled']
    .map((s) => ({ value: s, label: humanise(s) })),
]

const workshopColumns: Column<Booking>[] = [
  { key: 'reference', label: 'Booking', sortable: true },
  { key: 'customerName', label: 'Customer', sortable: true },
  { key: 'workshopTypeName', label: 'Workshop', sortable: true },
  { key: 'attendeeCount', label: 'Attendees', align: 'right' },
  { key: 'status', label: 'Status', sortable: true },
]
const diyColumns: Column<Booking>[] = [
  { key: 'reference', label: 'Order', sortable: true },
  { key: 'customerName', label: 'Customer', sortable: true },
  { key: 'diySpecs', label: 'Specification' },
  { key: 'scheduledDate', label: 'Target date', sortable: true },
  { key: 'status', label: 'Status', sortable: true },
]
const columns = computed(() => (tab.value === 'workshop' ? workshopColumns : diyColumns))

const counts = computed(() => ({
  workshop: bookings.value.filter((b) => b.type === 'workshop').length,
  diy_order: bookings.value.filter((b) => b.type === 'diy_order').length,
}))
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Bookings" description="Workshop places and custom DIY sandal orders." />

    <div class="flex items-center gap-5 border-b border-border">
      <button
        v-for="t in (['workshop', 'diy_order'] as const)" :key="t"
        type="button" class="-mb-px border-b-2 pb-2.5 text-ui transition-colors"
        :class="tab === t ? 'border-accent font-medium text-fg-strong' : 'border-transparent text-fg-muted hover:text-fg-strong'"
        @click="tab = t"
      >
        {{ t === 'workshop' ? 'Workshops' : 'DIY orders' }}
        <span class="ml-1.5 text-meta text-fg-faint">{{ counts[t] }}</span>
      </button>
    </div>

    <p v-if="tab === 'diy_order'" class="text-ui text-fg-muted">
      DIY orders are queue-based — they are never capacity-limited or turned away. Customers see
      the turnaround estimate for their order type at submission.
    </p>

    <UiToolbar
      v-model:search="r.search.value" :active-filter-count="r.activeFilterCount.value"
      placeholder="Search by reference, name or email…" @clear-filters="r.clearFilters()"
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
      empty-title="No bookings match"
      @sort="r.toggleSort"
    >
      <template #cell-reference="{ row }">
        <span class="block font-mono text-fg-strong">{{ row.reference }}</span>
        <span class="block text-meta text-fg-faint">{{ formatRelative(row.createdAt) }}</span>
      </template>
      <template #cell-customerName="{ row }">
        <span class="block truncate text-fg">{{ row.customerName }}</span>
        <span class="block truncate text-meta text-fg-faint">{{ row.customerPhone }}</span>
      </template>
      <template #cell-workshopTypeName="{ row }">
        <span class="text-fg-muted">{{ row.workshopTypeName }}</span>
      </template>
      <template #cell-attendeeCount="{ row }">
        <span class="text-fg-strong">{{ row.attendeeCount }}</span>
      </template>
      <template #cell-diySpecs="{ row }">
        <span v-if="row.diySpecs" class="text-fg-muted">
          Size {{ row.diySpecs.size }}<span v-if="row.diySpecs.footLengthCm"> · {{ row.diySpecs.footLengthCm }}cm</span>
          <span class="block text-meta text-fg-faint">
            {{ row.diySpecs.colourway }} · {{ row.diySpecs.soleMaterial }} ·
            {{ row.diySpecs.preferredFulfilment }}
          </span>
        </span>
      </template>
      <template #cell-scheduledDate="{ row }">
        <span class="text-fg-muted">{{ formatDate(row.scheduledDate) }}</span>
      </template>
      <template #cell-status="{ row }">
        <UiStatusBadge :status="row.status" />
        <span v-if="row.waitlistPosition" class="block text-meta text-fg-faint">
          #{{ row.waitlistPosition }} in queue
        </span>
      </template>
      <template #actions="{ row }">
        <UiPermissionGate capability="bookings.update_status" quiet>
          <UiButton v-if="row.status === 'pending'" variant="ghost" size="sm">Confirm</UiButton>
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
  </div>
</template>
