<script setup lang="ts">
import { PhArrowLeft } from '@phosphor-icons/vue'
import type { FxRate, InventoryItem, Product } from '~/types'
import { formatMoney, usdFrom } from '~/utils/currency'

/**
 * Product detail.
 *
 * The price section makes README Feature 2's rule visible: cedis are editable,
 * dollars are computed from the current rate and shown read-only. There is
 * deliberately no USD input — a field there would imply a stored value that
 * does not and must not exist.
 */
const route = useRoute()
const { useAdminItem, useAdminList } = useAdminApi()
const { can } = useAuth()
const { formatDate } = useFormatters()

const { item: product } = useAdminItem<Product>(`product-${route.params.id}`, `/admin/products/${route.params.id}`)
const { item: fx } = useAdminItem<FxRate>('product-fx', '/fx-rate')
const { items: inventory } = useAdminList<InventoryItem>('product-inventory', '/admin/inventory')

useHead({ title: computed(() => product.value?.name ?? 'Product') })

const variants = computed(() =>
  inventory.value.filter((i) => i.productId === product.value?.id),
)

const priceGhs = ref('')
watchEffect(() => {
  if (product.value) priceGhs.value = (product.value.basePriceGhs.amount / 100).toFixed(2)
})

const derivedUsd = computed(() => {
  if (!fx.value) return null
  const minor = Math.round(Number(priceGhs.value || 0) * 100)
  return usdFrom({ amount: minor, currency: 'GHS' }, fx.value)
})
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader :title="product?.name ?? 'Product'" :description="product?.sku">
      <template #actions>
        <UiButton variant="ghost" size="sm" to="/products">
          <PhArrowLeft :size="16" />
          All products
        </UiButton>
      </template>
    </UiPageHeader>

    <div v-if="!product" class="card">
      <UiEmptyState title="Product not found" />
    </div>

    <div v-else class="grid gap-4 xl:grid-cols-[minmax(0,1fr),320px]">
      <div class="admin-stack min-w-0">
        <SettingsSection title="Details">
          <div class="grid gap-4 sm:grid-cols-2">
            <UiField :model-value="product.name" label="Name" class="sm:col-span-2" />
            <UiField :model-value="product.slug" label="Slug" />
            <UiField :model-value="product.categoryName" label="Category" />
            <UiField :model-value="product.description" label="Description" type="textarea" class="sm:col-span-2" />
          </div>
          <template #footer>
            <UiPermissionGate capability="products.write" quiet>
              <UiButton size="sm">Save</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection
          title="Pricing"
          description="Cedis is the stored price. The dollar figure is derived from the current rate at display time and is never saved."
        >
          <div class="grid gap-4 sm:grid-cols-2">
            <UiField
              v-model="priceGhs" label="Base price (GHS)" type="number"
              :disabled="!can('pricing.write')"
            />
            <div>
              <p class="field-label">Derived price (USD)</p>
              <p class="flex min-h-[44px] items-center rounded-lg bg-bg-sunken px-3 text-ui text-fg-muted">
                {{ derivedUsd ? formatMoney(derivedUsd) : '—' }}
              </p>
              <p class="mt-1 text-meta text-fg-faint">
                Read-only. Moves with the rate; locked onto each order at checkout.
              </p>
            </div>
          </div>
          <template #footer>
            <UiPermissionGate capability="pricing.write">
              <UiButton size="sm">Save price</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection title="Variants" description="Stock per size and colourway.">
          <ul class="divide-y divide-border">
            <li v-for="v in variants" :key="v.id" class="flex flex-wrap items-center gap-3 py-2.5 first:pt-0 last:pb-0">
              <span class="min-w-0 flex-1">
                <span class="block text-ui text-fg-strong">
                  {{ Object.values(v.variantAttributes).join(' · ') }}
                </span>
                <span class="block font-mono text-meta text-fg-faint">{{ v.sku }}</span>
              </span>
              <span
                class="text-ui"
                :class="v.quantityAvailable <= v.lowStockThreshold ? 'font-medium text-warning' : 'text-fg'"
              >{{ v.quantityAvailable }} available</span>
              <span v-if="v.quantityReserved" class="text-meta text-info">
                {{ v.quantityReserved }} reserved
              </span>
            </li>
          </ul>
        </SettingsSection>
      </div>

      <aside class="flex flex-col gap-4">
        <SettingsSection title="Status">
          <UiBadge :tone="product.isActive ? 'success' : 'neutral'" dot>
            {{ product.isActive ? 'Active' : 'Inactive' }}
          </UiBadge>
          <UiBadge v-if="product.isFeatured" tone="accent" dot class="ml-1.5">Featured</UiBadge>
          <p class="mt-3 text-meta text-fg-muted">
            Inactive products are hidden from the storefront but stay visible here.
          </p>
          <dl class="mt-3 space-y-1.5 border-t border-border pt-3 text-meta">
            <div class="flex justify-between gap-2">
              <dt class="text-fg-faint">Created</dt><dd class="text-fg-muted">{{ formatDate(product.createdAt) }}</dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt class="text-fg-faint">Updated</dt><dd class="text-fg-muted">{{ formatDate(product.updatedAt) }}</dd>
            </div>
          </dl>
        </SettingsSection>
      </aside>
    </div>
  </div>
</template>
