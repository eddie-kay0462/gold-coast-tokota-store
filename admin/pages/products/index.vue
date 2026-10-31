<script setup lang="ts">
import { PhPencilSimple, PhPlus, PhTrash, PhPackage, PhX, PhInfo } from '@phosphor-icons/vue'
import type { FxRate, Product } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'
import { formatMoney, usdFrom } from '~/utils/currency'

/**
 * Products, Figma node 10:3761, followed closely: tabs (by state, below), the
 * sort-filter-history toolbar cluster, the dismissible info banner, a
 * checkbox table with a per-row action cluster, and the black primary action.
 *
 * The one substantive departure is the price column, which shows GHS with the
 * derived USD beneath. README Feature 2 forbids storing a USD price, so the
 * dollar figure here is computed from the cached rate at render time; showing
 * it read-only next to the editable cedi price makes that relationship
 * visible rather than something you have to know.
 */
useHead({ title: 'Products' })

const { useAdminList, useAdminItem, adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()
const { formatDate, formatNumber } = useFormatters()

const { items: products, pending, refresh } = useAdminList<Product>('admin-products', '/admin/products', { per_page: 200 }, { allPages: true })
const { item: fx } = useAdminItem<FxRate>('fx-rate', '/fx-rate')

// By state, all from fields the product already has: "Drafts" are products
// not on sale, which is how Add product saves a new one. There is no separate
// draft status to keep in step.
const tabs = [
  { key: 'all', label: 'All', test: () => true },
  { key: 'active', label: 'On sale', test: (p: Product) => p.isActive },
  { key: 'draft', label: 'Drafts', test: (p: Product) => !p.isActive },
  { key: 'out', label: 'Out of stock', test: (p: Product) => p.isActive && !p.isPreOrder && p.totalAvailable === 0 },
  { key: 'low', label: 'Low stock', test: (p: Product) => p.lowStock && p.totalAvailable > 0 },
  { key: 'preorder', label: 'Pre-order', test: (p: Product) => !!p.isPreOrder },
] as const
type TabKey = typeof tabs[number]['key']

// Kept in the address, so a tab survives a reload and opening a product and coming back.
const route = useRoute()
const router = useRouter()
const tab = computed<TabKey>({
  get: () => (tabs.some((t) => t.key === route.query.tab) ? route.query.tab as TabKey : 'active'),
  set: (key) => { router.replace({ query: { ...route.query, tab: key === 'active' ? undefined : key } }) },
})
const tabCount = (key: TabKey) => products.value.filter(tabs.find((t) => t.key === key)!.test).length
const visible = computed(() => products.value.filter(tabs.find((t) => t.key === tab.value)!.test))

const r = useResource<Product>(visible, {
  searchFields: ['name', 'sku', 'categoryName'],
  defaultSort: 'name',
  defaultDir: 'asc',
})

// Arriving from Categories filters to that one.
if (typeof route.query.category === 'string') r.setFilter('categoryName', route.query.category)

const bannerDismissed = useCookie<boolean>('gct-products-banner', { default: () => false })

const categoryOptions = computed(() => [
  { value: 'all', label: 'All categories' },
  ...[...new Set(products.value.map((p) => p.categoryName))].map((c) => ({ value: c, label: c })),
])

// --- Deactivate (bulk) and delete --------------------------------------------
const busy = ref(false)

async function setOnSale(onSale: boolean) {
  const ids = [...r.selected.value]
  busy.value = true
  let done = 0
  try {
    for (const id of ids) {
      await adminFetch(`/admin/products/${id}`, { method: 'PUT', body: { is_active: onSale } })
      done++
    }
    const plural = done === 1 ? '' : 's'
    if (onSale) toast.success(`${done} product${plural} on sale`, 'They’re in the shop now.')
    else toast.success(`${done} product${plural} taken off sale`, 'They stay here under Drafts.')
    r.clearSelection()
  } catch (err) {
    const message = failureMessage(err)
    if (message) toast.error(done ? `Stopped after ${done}` : 'Not saved', message)
  } finally {
    busy.value = false
    await refresh()
  }
}

const deleting = ref<Product | null>(null)
const deleteOpen = ref(false)
function askDelete(p: Product) {
  deleting.value = p
  deleteOpen.value = true
}
async function confirmDelete() {
  if (!deleting.value) return
  busy.value = true
  try {
    await adminFetch(`/admin/products/${deleting.value.id}`, { method: 'DELETE' })
    toast.success('Product deleted', deleting.value.name)
    deleteOpen.value = false
    await refresh()
  } catch (err) {
    const message = failureMessage(err, 'Not deleted. Try again in a moment.')
    if (message) toast.error('Not deleted', message)
  } finally {
    busy.value = false
  }
}

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
        <UiPermissionGate capability="products.write" quiet>
          <UiButton size="sm" to="/products/new">
            <PhPlus :size="16" />
            Add product
          </UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <!-- Tabs (Figma 10:5807) -->
    <div class="flex items-center gap-5 overflow-x-auto border-b border-border">
      <button
        v-for="t in tabs" :key="t.key"
        type="button"
        class="-mb-px shrink-0 border-b-2 pb-2.5 text-ui transition-colors"
        :class="tab === t.key
          ? 'border-accent font-medium text-fg-strong'
          : 'border-transparent text-fg-muted hover:text-fg-strong'"
        @click="tab = t.key; r.clearSelection()"
      >
        {{ t.label }}
        <span class="ml-1.5 text-meta text-fg-faint">{{ tabCount(t.key) }}</span>
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
        time and is never stored, so it moves with the rate, except on placed orders, where
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
      <UiPermissionGate capability="products.write" quiet>
        <UiButton v-if="tab === 'draft'" variant="ghost" size="sm" :loading="busy" @click="setOnSale(true)">Put on sale</UiButton>
        <UiButton v-else variant="ghost" size="sm" :loading="busy" @click="setOnSale(false)">Take off sale</UiButton>
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
      empty-description="Try a different search, or another tab."
      @sort="r.toggleSort"
      @toggle-row="r.toggleRow"
      @toggle-all="r.toggleAllOnPage"
    >
      <template #cell-name="{ row }">
        <span class="flex items-center gap-3">
          <img
            v-if="row.imageUrls?.length"
            :src="row.imageUrls[0]" alt="" loading="lazy"
            class="size-9 shrink-0 rounded bg-bg-sunken object-cover"
          >
          <span v-else class="flex size-9 shrink-0 items-center justify-center rounded bg-bg-sunken text-fg-faint">
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
          {{ row.isActive ? 'On sale' : 'Draft' }}
        </UiBadge>
        <UiBadge v-if="row.isPreOrder" tone="info" size="sm" class="ml-1">Pre-order</UiBadge>
      </template>

      <template #cell-updatedAt="{ row }">
        <span class="text-fg-muted">{{ formatDate(row.updatedAt) }}</span>
      </template>

      <template #actions="{ row }">
        <NuxtLink :to="`/products/${row.id}`" class="toolbar-btn" :aria-label="`Edit ${row.name}`">
          <PhPencilSimple :size="16" />
        </NuxtLink>
        <button
          v-if="can('products.delete')" type="button"
          class="toolbar-btn hover:text-danger" :aria-label="`Delete ${row.name}`"
          @click.stop="askDelete(row)"
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

    <UiModal v-model:open="deleteOpen" size="sm" title="Delete this product?" :description="deleting?.name">
      <p class="text-ui text-fg-muted">
        It goes from the shop and from here, with its sizes and stock. Past orders keep their
        details. To stop selling it but keep it, take it off sale instead.
      </p>
      <template #footer>
        <UiButton variant="secondary" size="sm" @click="deleteOpen = false">Keep it</UiButton>
        <UiButton variant="danger" size="sm" :loading="busy" @click="confirmDelete">Delete</UiButton>
      </template>
    </UiModal>
  </div>
</template>
