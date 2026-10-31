<script setup lang="ts">
import { PhArrowLeft } from '@phosphor-icons/vue'
import type { Product, VariantColour } from '~/types'

/**
 * Add a product: `POST /admin/products` with its sizes and colours, so its
 * stock rows exist from the start (at zero). Photos and shipping details are
 * added on the product's own page, which this opens once it's saved.
 *
 * It starts off sale: a product with no photos or stock shouldn't appear in
 * the shop the moment it's saved.
 */
useHead({ title: 'New product' })

const { adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()

const { options: categoryOptions, followType } = useCategories()
function setCategory(id: string) {
  form.productType = followType(form.productType, form.categoryId, id)
  form.categoryId = id
}

const form = reactive({
  name: '', slug: '', sku: '', categoryId: '', productType: '', priceGhs: '', description: '',
})
const sizes = ref<string[]>([])
const colours = ref<VariantColour[]>([])

// Slug and SKU follow the name until someone edits them by hand.
const slugTouched = ref(false)
const skuTouched = ref(false)
watch(() => form.name, (name) => {
  const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60)
  if (!slugTouched.value) form.slug = slug
  if (!skuTouched.value) form.sku = slug.replace(/-/g, '').toUpperCase().slice(0, 12)
})

const priceMinor = computed(() => Math.round(Number(form.priceGhs) * 100))
const valid = computed(() =>
  !!form.name.trim() && !!form.slug.trim() && !!form.sku.trim()
  && form.priceGhs !== '' && Number.isFinite(priceMinor.value) && priceMinor.value >= 0
  && sizes.value.length > 0 && colours.value.every((c) => c.name.trim()),
)

const saving = ref(false)
const error = ref<string | null>(null)

async function save() {
  if (!valid.value) return
  saving.value = true
  error.value = null
  try {
    const { data } = await adminFetch<Product>('/admin/products', {
      method: 'POST',
      body: {
        name: form.name.trim(),
        slug: form.slug.trim(),
        sku: form.sku.trim(),
        category_id: form.categoryId ? Number(form.categoryId) : null,
        product_type: form.productType.trim() || null,
        base_price_ghs: priceMinor.value,
        description: form.description.trim() || null,
        is_active: false,
        sizes: sizes.value,
        colours: colours.value.map((c) => ({ name: c.name.trim(), hex: c.hex })),
      },
    })
    toast.success('Product added', 'Add photos and stock, then put it on sale.')
    await navigateTo(`/products/${data.id}`)
  } catch (err) {
    error.value = failureMessage(err)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="New product" description="Saved off sale. Add photos and stock on the next page, then put it on sale.">
      <template #actions>
        <UiButton variant="ghost" size="sm" to="/products">
          <PhArrowLeft :size="16" />
          All products
        </UiButton>
      </template>
    </UiPageHeader>

    <UiPermissionGate capability="products.write">
      <form class="grid gap-4 xl:grid-cols-[minmax(0,1fr),320px]" @submit.prevent="save">
        <div class="admin-stack min-w-0">
          <SettingsSection title="Details">
            <div class="grid gap-4 sm:grid-cols-2">
              <UiField v-model="form.name" label="Name" required class="sm:col-span-2" />
              <div>
                <label for="new-slug" class="field-label">Slug</label>
                <input id="new-slug" v-model="form.slug" class="field w-full font-mono text-meta" required @input="slugTouched = true">
                <p class="mt-1 text-meta text-fg-faint">The end of its web address.</p>
              </div>
              <div>
                <label for="new-sku" class="field-label">SKU</label>
                <input id="new-sku" v-model="form.sku" class="field w-full font-mono text-meta" required @input="skuTouched = true">
                <p class="mt-1 text-meta text-fg-faint">Unique; for stock and orders.</p>
              </div>
              <UiSelect :model-value="form.categoryId" label="Category" :options="categoryOptions" @update:model-value="setCategory" />
              <UiField
                v-model="form.productType" label="Type" list="new-product-types"
                hint="Filled in from the category. The shop’s Type filter: slippers, ahenema, closed-toe."
              />
              <datalist id="new-product-types">
                <option v-for="t in ['slippers', 'ahenema', 'closed-toe']" :key="t" :value="t" />
              </datalist>
              <UiField v-model="form.description" label="Description" type="textarea" class="sm:col-span-2" />
            </div>
          </SettingsSection>

          <SettingsSection title="Sizes & colours" description="Every colour comes in every size. Each pair starts with no stock.">
            <ProductsVariantsEditor v-model:sizes="sizes" v-model:colours="colours" />
          </SettingsSection>
        </div>

        <aside class="flex flex-col gap-4">
          <SettingsSection title="Price" description="In cedis. The dollar price is worked out from the day’s rate.">
            <UiField v-model="form.priceGhs" label="Base price (GHS)" type="number" required :disabled="!can('pricing.write')" />
          </SettingsSection>

          <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ error }}</p>
          <UiButton type="submit" :loading="saving" :disabled="!valid" block>Add product</UiButton>
          <p v-if="!sizes.length" class="text-meta text-fg-faint">Add at least one size to save it.</p>
        </aside>
      </form>
    </UiPermissionGate>
  </div>
</template>
