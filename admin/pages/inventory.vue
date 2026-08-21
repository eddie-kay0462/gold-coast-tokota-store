<script setup lang="ts">
import { PhWarningCircle } from '@phosphor-icons/vue'
import type { InventoryItem } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'

/**
 * Inventory — stock by variant.
 *
 * Available and reserved are shown side by side rather than netted together.
 * README Feature 3 keeps them separate on purpose: reserved units are held by
 * an in-flight checkout for 15 minutes and are neither sellable nor sold, and
 * collapsing them into one number is how bulk adjustments overwrite live
 * reservations.
 */
useHead({ title: 'Inventory' })

const { useAdminList } = useAdminApi()
const { formatRelative, formatNumber } = useFormatters()
const { items: inventory, pending } = useAdminList<InventoryItem>('admin-inventory', '/admin/inventory')

const lowOnly = ref(false)
const visible = computed(() =>
  lowOnly.value
    ? inventory.value.filter((i) => i.quantityAvailable <= i.lowStockThreshold)
    : inventory.value,
)

const r = useResource<InventoryItem>(visible, {
  searchFields: ['productName', 'sku'],
  defaultSort: 'quantityAvailable',
  defaultDir: 'asc',
  perPage: 15,
})

const lowCount = computed(
  () => inventory.value.filter((i) => i.quantityAvailable <= i.lowStockThreshold).length,
)

const columns: Column<InventoryItem>[] = [
  { key: 'productName', label: 'Product', sortable: true },
  { key: 'variantAttributes', label: 'Variant' },
  { key: 'quantityAvailable', label: 'Available', sortable: true, align: 'right' },
  { key: 'quantityReserved', label: 'Reserved', sortable: true, align: 'right' },
  { key: 'lowStockThreshold', label: 'Threshold', align: 'right', hideOnCard: true },
  { key: 'updatedAt', label: 'Updated', sortable: true, hideOnCard: true },
]

const variantLabel = (v: Record<string, string>) =>
  Object.entries(v).map(([k, val]) => `${k} ${val}`).join(' · ')
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Inventory" description="Stock levels per variant. Reserved units are held by an in-flight checkout." />

    <NuxtLink
      v-if="lowCount && !lowOnly"
      to="#" class="flex items-center gap-2.5 rounded-lg border border-warning/30 bg-warning-soft px-3.5 py-2.5 text-ui text-warning"
      @click.prevent="lowOnly = true"
    >
      <PhWarningCircle :size="18" class="shrink-0" />
      {{ lowCount }} variants are at or below their low-stock threshold — show only those
    </NuxtLink>

    <UiToolbar
      v-model:search="r.search.value" :active-filter-count="lowOnly ? 1 : 0"
      placeholder="Search by product or SKU…" @clear-filters="lowOnly = false; r.clearFilters()"
    >
      <template #actions>
        <label class="flex cursor-pointer items-center gap-2 text-ui text-fg-muted">
          <input v-model="lowOnly" type="checkbox" class="size-4 rounded-sm border-border-strong text-accent focus:ring-accent">
          Low stock only
        </label>
      </template>
    </UiToolbar>

    <UiDataTable
      :columns="columns" :rows="r.paged.value" :loading="pending"
      :sort="r.sort.value" :dir="r.dir.value"
      empty-title="No variants match"
      empty-description="Everything is above its threshold, or the search returned nothing."
      @sort="r.toggleSort"
    >
      <template #cell-productName="{ row }">
        <span class="block truncate text-fg-strong">{{ row.productName }}</span>
        <span class="block truncate text-meta text-fg-faint">{{ row.sku }}</span>
      </template>
      <template #cell-variantAttributes="{ row }">
        <span class="text-fg-muted">{{ variantLabel(row.variantAttributes) }}</span>
      </template>
      <template #cell-quantityAvailable="{ row }">
        <span
          class="text-ui"
          :class="row.quantityAvailable <= row.lowStockThreshold ? 'font-medium text-warning' : 'text-fg-strong'"
        >{{ formatNumber(row.quantityAvailable) }}</span>
      </template>
      <template #cell-quantityReserved="{ row }">
        <span :class="row.quantityReserved ? 'text-info' : 'text-fg-faint'">
          {{ row.quantityReserved || '—' }}
        </span>
        <span v-if="row.reservationExpiresAt" class="block text-meta text-fg-faint">expires soon</span>
      </template>
      <template #cell-lowStockThreshold="{ row }">
        <span class="text-fg-faint">{{ row.lowStockThreshold }}</span>
      </template>
      <template #cell-updatedAt="{ row }">
        <span class="text-fg-muted">{{ formatRelative(row.updatedAt) }}</span>
      </template>
      <template #actions>
        <UiPermissionGate capability="inventory.adjust" quiet>
          <UiButton variant="ghost" size="sm">Adjust</UiButton>
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
