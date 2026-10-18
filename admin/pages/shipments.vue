<script setup lang="ts">
import type { Order, ShipmentStatus } from '~/types'
import { SHIPMENT_STATUSES } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'
import { humanise } from '~/utils/formatters'

/**
 * Shipments: every paid order and its delivery booking, waiting-to-go first.
 *
 * The courier is decided by the API from the destination: a pin inside the
 * Accra zone goes to Yango (Swoove as fallback), the rest of Ghana at its
 * regional rate by hand, everywhere else by DHL. While a courier's API is
 * switched off its zone is dispatched by hand, which is what "By hand" means
 * here. "Needs attention" is the failed bookings and anything still waiting
 * past the 48 hours §21 allows for processing.
 */
useHead({ title: 'Shipments' })

const { useAdminList, useAdminItem } = useAdminApi()
const { formatRelative } = useFormatters()

const attentionOnly = ref(false)
const query = computed(() => (attentionOnly.value ? { attention: 1 } : {}))
const { items: orders, pending, refresh } = useAdminList<Order>('admin-shipments', '/admin/shipments', query)
const { item: attention, refresh: refreshAttention } = useAdminItem<{ count: number }>(
  'admin-shipments-attention', '/admin/shipments/attention',
)

const CARRIERS: Record<string, string> = {
  manual: 'By hand', yango: 'Yango', swoove: 'Swoove', dhl: 'DHL Express',
}
const ZONES: Record<string, string> = { accra: 'Accra', ghana: 'Ghana', international: 'International' }

type Row = {
  id: number
  reference: string
  destination: string
  zone: string
  carrier: string
  courier: string
  shipmentStatus: ShipmentStatus | 'none'
  tracking: string
  dispatchedAt: string | null
  placedAt: string
  order: Order
}

const rows = computed<Row[]>(() => orders.value.map((o) => ({
  id: o.id,
  reference: o.reference,
  destination: [o.shippingAddress.city, o.shippingAddress.country].filter(Boolean).join(', '),
  zone: o.shippingZone ?? o.shipment?.zone ?? '',
  carrier: o.shipment?.carrier ?? o.deliveryProvider ?? '',
  courier: o.shipment?.courierName ?? CARRIERS[o.shipment?.carrier ?? o.deliveryProvider ?? ''] ?? 'Not booked',
  shipmentStatus: o.shipment?.status ?? 'none',
  tracking: o.shipment?.carrierReference ?? o.deliveryReference ?? '',
  dispatchedAt: o.shipment?.dispatchedAt ?? null,
  placedAt: o.placedAt,
  order: o,
})))

const r = useResource<Row>(rows, {
  searchFields: ['reference', 'tracking', 'destination', 'courier'],
  defaultSort: 'placedAt',
  defaultDir: 'desc',
})

const carrierOptions = [
  { value: 'all', label: 'All couriers' },
  ...Object.entries(CARRIERS).map(([value, label]) => ({ value, label })),
]
const statusOptions = [
  { value: 'all', label: 'All statuses' },
  ...SHIPMENT_STATUSES.map((s) => ({ value: s, label: humanise(s) })),
]

const columns: Column<Row>[] = [
  { key: 'reference', label: 'Order', sortable: true },
  { key: 'courier', label: 'Courier', sortable: true },
  { key: 'destination', label: 'Destination', sortable: true },
  { key: 'shipmentStatus', label: 'Status', sortable: true },
]

const active = ref<Order | null>(null)
const drawerOpen = ref(false)
function openRow(row: Row) { active.value = row.order; drawerOpen.value = true }

function onChanged() {
  refresh()
  refreshAttention()
}
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader
      title="Shipments"
      description="Each order's courier is picked from where it is going: the Accra pin zone, the rest of Ghana, or abroad."
    />

    <UiToolbar
      v-model:search="r.search.value" :active-filter-count="r.activeFilterCount.value"
      placeholder="Search by order, tracking or destination…" @clear-filters="r.clearFilters()"
    >
      <template #actions>
        <UiButton
          :variant="attentionOnly ? 'primary' : 'secondary'" size="sm"
          :aria-pressed="attentionOnly" @click="attentionOnly = !attentionOnly"
        >
          Needs attention
          <UiBadge v-if="attention?.count" tone="danger" size="sm">{{ attention.count }}</UiBadge>
        </UiButton>
        <UiSelect
          :model-value="r.filters.value.carrier ?? 'all'" :options="carrierOptions" class="w-44"
          @update:model-value="r.setFilter('carrier', $event)"
        />
        <UiSelect
          :model-value="r.filters.value.shipmentStatus ?? 'all'" :options="statusOptions" class="w-44"
          @update:model-value="r.setFilter('shipmentStatus', $event)"
        />
      </template>
    </UiToolbar>

    <UiDataTable
      :columns="columns" :rows="r.paged.value" :loading="pending"
      :sort="r.sort.value" :dir="r.dir.value"
      :empty-title="attentionOnly ? 'Nothing needs attention' : 'No shipments match'"
      @sort="r.toggleSort"
    >
      <template #cell-reference="{ row }">
        <button type="button" class="block font-mono text-fg-strong underline-offset-4 hover:underline" @click="openRow(row)">
          {{ row.reference }}
        </button>
        <span v-if="row.tracking" class="block font-mono text-meta text-fg-faint">{{ row.tracking }}</span>
      </template>
      <template #cell-courier="{ row }">
        <UiBadge :tone="row.carrier === 'manual' ? 'neutral' : 'info'">{{ CARRIERS[row.carrier] ?? 'Not booked' }}</UiBadge>
        <span v-if="row.carrier === 'manual' && row.order.shipment?.courierName" class="block text-meta text-fg-faint">
          {{ row.order.shipment.courierName }}
        </span>
      </template>
      <template #cell-destination="{ row }">
        <span class="text-fg">{{ row.destination }}</span>
        <span class="block text-meta text-fg-faint">
          {{ ZONES[row.zone] ?? '' }}<template v-if="row.zone"> · </template>
          {{ row.dispatchedAt ? `Dispatched ${formatRelative(row.dispatchedAt)}` : `Paid ${formatRelative(row.placedAt)}` }}
        </span>
      </template>
      <template #cell-shipmentStatus="{ row }">
        <UiStatusBadge v-if="row.shipmentStatus !== 'none'" :status="row.shipmentStatus" />
        <span v-else class="text-meta text-fg-faint">No shipment</span>
      </template>
      <template #footer>
        <UiPagination
          :page="r.page.value" :page-count="r.pageCount.value"
          :total="r.total.value" :per-page="r.perPage.value"
          @update:page="r.page.value = $event"
        />
      </template>
    </UiDataTable>

    <OrdersOrderDrawer v-model:open="drawerOpen" :order="active" @changed="onChanged" />
  </div>
</template>
