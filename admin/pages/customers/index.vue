<script setup lang="ts">
import { PhDownloadSimple } from '@phosphor-icons/vue'
import type { Customer } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'
import { formatMoney } from '~/utils/currency'

useHead({ title: 'Customers' })

const { useAdminList } = useAdminApi()
const { formatRelative } = useFormatters()
const { items: customers, pending } = useAdminList<Customer>('admin-customers', '/admin/customers')

const r = useResource<Customer>(customers, {
  searchFields: ['name', 'email', 'country'],
  defaultSort: 'lastOrderAt',
  defaultDir: 'desc',
})

const accountOptions = [
  { value: 'all', label: 'All customers' },
  { value: 'true', label: 'With an account' },
  { value: 'false', label: 'Guest only' },
]

const columns: Column<Customer>[] = [
  { key: 'name', label: 'Customer', sortable: true },
  { key: 'country', label: 'Location', sortable: true },
  { key: 'orderCount', label: 'Orders', sortable: true, align: 'right' },
  { key: 'lifetimeValue', label: 'Lifetime value', align: 'right' },
  { key: 'lastOrderAt', label: 'Last order', sortable: true },
]
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Customers" description="Everyone who has ordered, including guest checkouts.">
      <template #actions>
        <UiPermissionGate capability="customers.export" quiet>
          <UiButton variant="secondary" size="sm">
            <PhDownloadSimple :size="16" />
            Export
          </UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <UiToolbar
      v-model:search="r.search.value" :active-filter-count="r.activeFilterCount.value"
      placeholder="Search by name, email or country…" @clear-filters="r.clearFilters()"
    >
      <template #actions>
        <UiSelect
          :model-value="r.filters.value.hasAccount ?? 'all'" :options="accountOptions" class="w-44"
          @update:model-value="r.setFilter('hasAccount', $event)"
        />
      </template>
    </UiToolbar>

    <UiDataTable
      :columns="columns" :rows="r.paged.value" :loading="pending"
      :sort="r.sort.value" :dir="r.dir.value" :row-link="(c) => `/customers/${c.id}`"
      empty-title="No customers match"
      @sort="r.toggleSort"
    >
      <template #cell-name="{ row }">
        <span class="flex items-center gap-3">
          <UiAvatar :name="row.name" :size="32" />
          <span class="min-w-0">
            <span class="block truncate">{{ row.name }}</span>
            <span class="block truncate text-meta font-normal text-fg-faint">{{ row.email }}</span>
          </span>
        </span>
      </template>
      <template #cell-country="{ row }">
        <span class="text-fg-muted">{{ row.country }}</span>
        <UiBadge v-if="!row.hasAccount" tone="outline" size="sm" class="ml-1.5">Guest</UiBadge>
      </template>
      <template #cell-orderCount="{ row }">
        <span :class="row.orderCount ? 'text-fg-strong' : 'text-fg-faint'">{{ row.orderCount }}</span>
      </template>
      <template #cell-lifetimeValue="{ row }">
        <span class="text-fg-strong">{{ formatMoney(row.lifetimeValue) }}</span>
        <span class="block text-meta text-fg-faint">prefers {{ row.preferredCurrency }}</span>
      </template>
      <template #cell-lastOrderAt="{ row }">
        <span class="text-fg-muted">{{ row.lastOrderAt ? formatRelative(row.lastOrderAt) : 'Never' }}</span>
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
