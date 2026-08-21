<script setup lang="ts">
import { PhDownloadSimple, PhKanban } from '@phosphor-icons/vue'
import type { Order } from '~/types'
import { ORDER_STATUSES } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'
import { formatMoney } from '~/utils/currency'
import { humanise } from '~/utils/formatters'

/**
 * Orders — status filter plus search by customer name, email or reference,
 * per README Feature 9's acceptance criteria. Row click opens the detail
 * drawer rather than navigating away, so working a queue keeps your place.
 */
useHead({ title: 'Orders' })

const { useAdminList } = useAdminApi()
const { formatDateTime } = useFormatters()
const { items: orders, pending } = useAdminList<Order>('admin-orders', '/admin/orders')

const r = useResource<Order>(orders, {
  searchFields: ['reference', 'customerName', 'customerEmail'],
  defaultSort: 'placedAt',
  defaultDir: 'desc',
})

const statusOptions = [
  { value: 'all', label: 'All statuses' },
  ...ORDER_STATUSES.map((s) => ({ value: s, label: humanise(s) })),
]
const currencyOptions = [
  { value: 'all', label: 'Both currencies' },
  { value: 'GHS', label: 'GHS · Paystack' },
  { value: 'USD', label: 'USD · Stripe' },
]

const columns: Column<Order>[] = [
  { key: 'reference', label: 'Order', sortable: true },
  { key: 'customerName', label: 'Customer', sortable: true },
  { key: 'placedAt', label: 'Placed', sortable: true },
  { key: 'total', label: 'Total', sortable: false, align: 'right' },
  { key: 'status', label: 'Status', sortable: true },
]

const active = ref<Order | null>(null)
const drawerOpen = ref(false)
function openOrder(o: Order) { active.value = o; drawerOpen.value = true }
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Orders" description="Every order across both currencies and both couriers.">
      <template #actions>
        <UiButton variant="secondary" size="sm" to="/orders/board">
          <PhKanban :size="16" />
          Fulfilment board
        </UiButton>
        <UiButton variant="secondary" size="sm">
          <PhDownloadSimple :size="16" />
          Export
        </UiButton>
      </template>
    </UiPageHeader>

    <UiToolbar
      v-model:search="r.search.value"
      :active-filter-count="r.activeFilterCount.value"
      placeholder="Search by reference, name or email…"
      @clear-filters="r.clearFilters()"
    >
      <template #actions>
        <UiSelect
          :model-value="r.filters.value.status ?? 'all'" :options="statusOptions" class="w-44"
          @update:model-value="r.setFilter('status', $event)"
        />
        <UiSelect
          :model-value="r.filters.value.currency ?? 'all'" :options="currencyOptions" class="w-44"
          @update:model-value="r.setFilter('currency', $event)"
        />
      </template>
    </UiToolbar>

    <UiDataTable
      :columns="columns" :rows="r.paged.value" :loading="pending"
      :sort="r.sort.value" :dir="r.dir.value"
      empty-title="No orders match"
      empty-description="Try clearing the status filter or searching for a different customer."
      @sort="r.toggleSort"
    >
      <template #cell-reference="{ row }">
        <button type="button" class="font-mono text-fg-strong underline-offset-4 hover:underline" @click="openOrder(row)">
          {{ row.reference }}
        </button>
      </template>
      <template #cell-customerName="{ row }">
        <span class="block truncate text-fg">{{ row.customerName }}</span>
        <span class="block truncate text-meta text-fg-faint">
          {{ row.shippingAddress.country }} · {{ row.deliveryProvider.toUpperCase() }}
        </span>
      </template>
      <template #cell-placedAt="{ row }">
        <span class="text-fg-muted">{{ formatDateTime(row.placedAt) }}</span>
      </template>
      <template #cell-total="{ row }">
        <span class="text-fg-strong">{{ formatMoney(row.total) }}</span>
        <span class="block text-meta text-fg-faint">{{ row.items.length }} item{{ row.items.length === 1 ? '' : 's' }}</span>
      </template>
      <template #cell-status="{ row }">
        <UiStatusBadge :status="row.status" />
      </template>
      <template #actions="{ row }">
        <UiButton variant="ghost" size="sm" @click="openOrder(row)">View</UiButton>
      </template>
      <template #footer>
        <UiPagination
          :page="r.page.value" :page-count="r.pageCount.value"
          :total="r.total.value" :per-page="r.perPage.value"
          @update:page="r.page.value = $event"
        />
      </template>
    </UiDataTable>

    <OrdersOrderDrawer v-model:open="drawerOpen" :order="active" />
  </div>
</template>
