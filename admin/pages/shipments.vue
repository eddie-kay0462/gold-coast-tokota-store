<script setup lang="ts">
import type { Shipment } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'
import { humanise } from '~/utils/formatters'

/**
 * Shipments. The provider column is derived, never chosen: README Feature 5
 * routes Ghana addresses to Yango and everything else to DHL, so showing it
 * alongside the destination makes a mis-route obvious at a glance.
 */
useHead({ title: 'Shipments' })

const { useAdminList } = useAdminApi()
const { formatRelative } = useFormatters()
const { items: shipments, pending } = useAdminList<Shipment>('admin-shipments', '/admin/shipments')

const r = useResource<Shipment>(shipments, {
  searchFields: ['orderReference', 'trackingReference', 'destination'],
  defaultSort: 'updatedAt',
  defaultDir: 'desc',
})

const providerOptions = [
  { value: 'all', label: 'Both couriers' },
  { value: 'yango', label: 'Yango · domestic' },
  { value: 'dhl', label: 'DHL · international' },
]
const statusOptions = [
  { value: 'all', label: 'All statuses' },
  ...['awaiting_pickup', 'in_transit', 'customs', 'out_for_delivery', 'delivered', 'exception']
    .map((s) => ({ value: s, label: humanise(s) })),
]

const columns: Column<Shipment>[] = [
  { key: 'orderReference', label: 'Order', sortable: true },
  { key: 'provider', label: 'Courier', sortable: true },
  { key: 'destination', label: 'Destination', sortable: true },
  { key: 'etaLabel', label: 'ETA', hideOnCard: true },
  { key: 'status', label: 'Status', sortable: true },
]
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader
      title="Shipments"
      description="Ghana addresses go via Yango, everywhere else via DHL — routed automatically from the shipping country."
    />

    <UiToolbar
      v-model:search="r.search.value" :active-filter-count="r.activeFilterCount.value"
      placeholder="Search by order, tracking or destination…" @clear-filters="r.clearFilters()"
    >
      <template #actions>
        <UiSelect
          :model-value="r.filters.value.provider ?? 'all'" :options="providerOptions" class="w-48"
          @update:model-value="r.setFilter('provider', $event)"
        />
        <UiSelect
          :model-value="r.filters.value.status ?? 'all'" :options="statusOptions" class="w-44"
          @update:model-value="r.setFilter('status', $event)"
        />
      </template>
    </UiToolbar>

    <UiDataTable
      :columns="columns" :rows="r.paged.value" :loading="pending"
      :sort="r.sort.value" :dir="r.dir.value"
      empty-title="No shipments match"
      @sort="r.toggleSort"
    >
      <template #cell-orderReference="{ row }">
        <span class="block font-mono text-fg-strong">{{ row.orderReference }}</span>
        <span class="block font-mono text-meta text-fg-faint">{{ row.trackingReference }}</span>
      </template>
      <template #cell-provider="{ row }">
        <UiBadge :tone="row.provider === 'yango' ? 'info' : 'neutral'">
          {{ row.provider.toUpperCase() }}
        </UiBadge>
      </template>
      <template #cell-destination="{ row }">
        <span class="text-fg">{{ row.destination }}</span>
        <span class="block text-meta text-fg-faint">
          {{ row.dispatchedAt ? `Dispatched ${formatRelative(row.dispatchedAt)}` : 'Not dispatched' }}
        </span>
      </template>
      <template #cell-etaLabel="{ row }">
        <span class="text-fg-muted">{{ row.etaLabel }}</span>
      </template>
      <template #cell-status="{ row }">
        <UiStatusBadge :status="row.status" />
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
