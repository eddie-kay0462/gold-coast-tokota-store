<script setup lang="ts">
import { PhDownloadSimple, PhCopy, PhPencilSimple, PhPlus, PhTrash, PhPackage, PhX, PhInfo } from '@phosphor-icons/vue'
import type { FxRate, Product } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'
import { formatMoney, usdFrom } from '~/utils/currency'

/**
 * Products — Figma node 10:3761, followed closely: Active / All tabs, the
 * sort-filter-history toolbar cluster, the dismissible info banner, a
 * checkbox table with a per-row action cluster, and the black primary action.
 *
 * The one substantive departure is the price column, which shows GHS with the
 * derived USD beneath. README Feature 2 forbids storing a USD price, so the
 * dollar figure here is computed from the cached rate at render time — showing
 * it read-only next to the editable cedi price makes that relationship
 * visible rather than something you have to know.
 */
useHead({ title: 'Products' })

const { useAdminList, useAdminItem } = useAdminApi()
const { can } = useAuth()
const { formatDate, formatNumber } = useFormatters()

const { items: products, pending } = useAdminList<Product>('admin-products', '/admin/products')
const { item: fx } = useAdminItem<FxRate>('fx-rate', '/fx-rate')

const tab = ref<'active' | 'all'>('active')
const visible = computed(() =>
  tab.value === 'active' ? products.value.filter((p) => p.isActive) : products.value,
)

const r = useResource<Product>(visible, {
  searchFields: ['name', 'sku', 'categoryName'],
  defaultSort: 'name',
  defaultDir: 'asc',
})

const bannerDismissed = useCookie<boolean>('gct-products-banner', { default: () => false })

const categoryOptions = computed(() => [
  { value: 'all', label: 'All categories' },
  ...[...new Set(products.value.map((p) => p.categoryName))].map((c) => ({ value: c, label: c })),
])

const columns: Column<Product>[] = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'categoryName', label: 'Category', sortable: true },
  { key: 'totalAvailable', label: 'Stock', sortable: true, align: 'right' },
  { key: 'basePriceGhs', label: 'Price', sortable: false, align: 'right' },
  { key: 'isActive', label: 'Status' },
  { key: 'updatedAt', label: 'Updated', sortable: true, hideOnCard: true },
]
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Products" description="Everything in the catalogue, across all categories.">
      <template #actions>
        <UiButton variant="secondary" size="sm">
          <PhDownloadSimple :size="16" />
          Import
        </UiButton>
        <UiPermissionGate capability="products.write" quiet>
          <UiButton size="sm">
            <PhPlus :size="16" />
            Add product
          </UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <!-- Tabs (Figma 10:5807) -->
    <div class="flex items-center gap-5 border-b border-border">
      <button
        v-for="t in (['active', 'all'] as const)" :key="t"
        type="button"
        class="-mb-px border-b-2 pb-2.5 text-ui transition-colors"
        :class="tab === t
          ? 'border-accent font-medium text-fg-strong'
          : 'border-transparent text-fg-muted hover:text-fg-strong'"
        @click="tab = t"
      >
        {{ t === 'active' ? 'Active' : 'All products' }}
        <span class="ml-1.5 text-meta text-fg-faint">
          {{ t === 'active' ? products.filter((p) => p.isActive).length : products.length }}
        </span>
      </button>
    </div>

    <!-- Info banner (Figma 10:5807) -->
    <div
      v-if="!bannerDismissed"
      class="flex items-start gap-3 rounded-lg border border-border bg-bg-sunken px-4 py-3"
    >
      <PhInfo :size="18" class="mt-px shrink-0 text-fg-faint" />
      <p class="min-w-0 flex-1 text-ui text-fg-muted">
        Prices are set in cedis. The dollar figure is derived from the live FX rate at display
        time and is never stored, so it moves with the rate — except on placed orders, where
        the rate is locked.
      </p>
      <button type="button" class="toolbar-btn -mr-1.5 -mt-1 shrink-0" aria-label="Dismiss" @click="bannerDismissed = true">
        <PhX :size="16" />
      </button>
    </div>

    <UiToolbar
      v-model:search="r.search.value"
      :active-filter-count="r.activeFilterCount.value"
      placeholder="Search by name, SKU or category…"
      @clear-filters="r.clearFilters()"
    >
      <template #actions>
        <UiSelect
          :model-value="r.filters.value.categoryName ?? 'all'"
          :options="categoryOptions"
          class="w-44"
          @update:model-value="r.setFilter('categoryName', $event)"
        />
      </template>
    </UiToolbar>

    <div v-if="r.selectedCount.value" class="flex items-center gap-3 rounded-lg bg-accent-soft px-4 py-2.5">
      <p class="text-ui text-accent-text">{{ r.selectedCount.value }} selected</p>
      <UiPermissionGate capability="products.delete" quiet>
        <UiButton variant="ghost" size="sm">Deactivate</UiButton>
      </UiPermissionGate>
      <button type="button" class="ml-auto text-meta text-accent-text hover:underline" @click="r.clearSelection()">
        Clear
      </button>
    </div>

    <UiDataTable
      :columns="columns"
      :rows="r.paged.value"
      :loading="pending"
      selectable
      :selected="r.selected.value"
      :sort="r.sort.value"
      :dir="r.dir.value"
      :row-link="(p) => `/products/${p.id}`"
      empty-title="No products match"
      empty-description="Try a different search, or switch to All products to include deactivated items."
      @sort="r.toggleSort"
      @toggle-row="r.toggleRow"
      @toggle-all="r.toggleAllOnPage"
    >
      <template #cell-name="{ row }">
        <span class="flex items-center gap-3">
          <span class="flex size-9 shrink-0 items-center justify-center rounded bg-bg-sunken text-fg-faint">
            <PhPackage :size="18" />
          </span>
          <span class="min-w-0">
            <span class="block truncate">{{ row.name }}</span>
            <span class="block truncate text-meta font-normal text-fg-faint">{{ row.sku }}</span>
          </span>
        </span>
      </template>

      <template #cell-totalAvailable="{ row }">
        <span :class="row.lowStock ? 'font-medium text-warning' : 'text-fg'">
          {{ formatNumber(row.totalAvailable) }}
        </span>
        <span v-if="row.totalReserved" class="block text-meta text-fg-faint">
          {{ row.totalReserved }} reserved
        </span>
      </template>

      <template #cell-basePriceGhs="{ row }">
        <span class="block text-fg-strong">{{ formatMoney(row.basePriceGhs) }}</span>
        <span v-if="fx" class="block text-meta text-fg-faint">
          ≈ {{ formatMoney(usdFrom(row.basePriceGhs, fx)) }}
        </span>
      </template>

      <template #cell-isActive="{ row }">
        <UiBadge :tone="row.isActive ? 'success' : 'neutral'" dot>
          {{ row.isActive ? 'Active' : 'Inactive' }}
        </UiBadge>
      </template>

      <template #cell-updatedAt="{ row }">
        <span class="text-fg-muted">{{ formatDate(row.updatedAt) }}</span>
      </template>

      <template #actions="{ row }">
        <button type="button" class="toolbar-btn" :aria-label="`Duplicate ${row.name}`">
          <PhCopy :size="16" />
        </button>
        <NuxtLink :to="`/products/${row.id}`" class="toolbar-btn" :aria-label="`Edit ${row.name}`">
          <PhPencilSimple :size="16" />
        </NuxtLink>
        <button
          v-if="can('products.delete')" type="button"
          class="toolbar-btn hover:text-danger" :aria-label="`Delete ${row.name}`"
        >
          <PhTrash :size="16" />
        </button>
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
