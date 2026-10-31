<script setup lang="ts">
import { PhArrowLeft, PhPlus, PhTrash, PhX } from '@phosphor-icons/vue'
import type { FxRate, InventoryItem, Product, VariantColour } from '~/types'
import type { LightboxImage } from '~/components/ui/Lightbox.vue'
import { formatMoney, usdFrom } from '~/utils/currency'

/**
 * Product detail.
 *
 * The price section makes README Feature 2's rule visible: cedis are editable,
 * dollars are computed from the current rate and shown read-only. There is
 * deliberately no USD input; a field there would imply a stored value that
 * does not and must not exist.
 */
const route = useRoute()
const { useAdminItem, useAdminList, adminFetch } = useAdminApi()
const toast = useToast()
const { can } = useAuth()
const { formatDate } = useFormatters()

const { item: product, refresh } = useAdminItem<Product>(`product-${route.params.id}`, `/admin/products/${route.params.id}`)
const { item: fx } = useAdminItem<FxRate>('product-fx', '/fx-rate')
// Filtered to this product: unfiltered, the list is the 50 scarcest sizes in
// the whole catalogue, which can leave this one's out.
const { items: inventory, refresh: refreshInventory } = useAdminList<InventoryItem>(
  `product-inventory-${route.params.id}`, '/admin/inventory', { product_id: route.params.id, per_page: 500 },
)
const { options: categoryOptions } = useCategories()

useHead({ title: computed(() => product.value?.name ?? 'Product') })

const variants = computed(() =>
  inventory.value.filter((i) => i.productId === product.value?.id),
)

const priceGhs = ref('')
watchEffect(() => {
  if (product.value) priceGhs.value = (product.value.basePriceGhs.amount / 100).toFixed(2)
})

// --- Sizes and colours ----------------------------------------------------
// `PUT /admin/products/{id}/variants`. The stock rows follow the two lists; a
// size or colour that's removed while it still has stock is refused until the
// admin confirms (`remove_stocked`), and one with units held for a checkout is
// refused outright.
const variantSizes = ref<string[]>([])
const variantColours = ref<VariantColour[]>([])
function resetVariants() {
  variantSizes.value = [...(product.value?.sizes ?? [])]
  variantColours.value = (product.value?.colours ?? []).map((c) => ({ ...c }))
}
watch(product, resetVariants, { immediate: true })
const savingVariants = ref(false)
const variantsError = ref<string | null>(null)
const stockWarning = ref<string | null>(null)
const variantsChanged = computed(() =>
  JSON.stringify(variantSizes.value) !== JSON.stringify(product.value?.sizes ?? [])
  || JSON.stringify(variantColours.value) !== JSON.stringify(product.value?.colours ?? []),
)

async function saveVariants(removeStocked = false) {
  if (!product.value) return
  if (variantColours.value.some((c) => !c.name.trim())) {
    variantsError.value = 'Give every colour a name, or remove it.'
    return
  }
  savingVariants.value = true
  variantsError.value = null
  try {
    await adminFetch(`/admin/products/${product.value.id}/variants`, {
      method: 'PUT',
      body: {
        sizes: variantSizes.value,
        colours: variantColours.value.map((c) => ({ name: c.name.trim(), hex: c.hex })),
        remove_stocked: removeStocked,
      },
    })
    stockWarning.value = null
    await Promise.all([refresh(), refreshInventory()])
    toast.success('Sizes and colours saved', 'New ones start at zero; set their stock in Inventory.')
  } catch (err) {
    const e = err as { data?: { errors?: Record<string, string[]> } }
    const warning = e.data?.errors?.remove_stocked?.[0]
    if (warning) stockWarning.value = warning
    else variantsError.value = failureMessage(err)
  } finally {
    savingVariants.value = false
  }
}

// --- Details and price ----------------------------------------------------
const details = reactive({ name: '', slug: '', categoryId: '', productType: '', description: '', isActive: true, isFeatured: false })
watchEffect(() => {
  const p = product.value
  if (!p) return
  Object.assign(details, {
    name: p.name, slug: p.slug, categoryId: p.categoryId ? String(p.categoryId) : '',
    productType: p.productType ?? '',
    description: p.description ?? '', isActive: p.isActive, isFeatured: p.isFeatured,
  })
})
const saving = ref<'details' | 'price' | 'delete' | null>(null)
const detailsError = ref<string | null>(null)
const priceError = ref<string | null>(null)

async function update(section: 'details' | 'price', body: Record<string, unknown>, done: string) {
  if (!product.value) return
  saving.value = section
  const error = section === 'details' ? detailsError : priceError
  error.value = null
  try {
    await adminFetch(`/admin/products/${product.value.id}`, { method: 'PUT', body })
    await refresh()
    toast.success(done, 'Live on the storefront now.')
  } catch (err) {
    error.value = failureMessage(err)
  } finally {
    saving.value = null
  }
}

function saveDetails() {
  return update('details', {
    name: details.name.trim(),
    slug: details.slug.trim(),
    category_id: details.categoryId ? Number(details.categoryId) : null,
    product_type: details.productType.trim() || null,
    description: details.description.trim() || null,
    is_active: details.isActive,
    is_featured: details.isFeatured,
  }, 'Product saved')
}

const priceMinor = computed(() => Math.round(Number(priceGhs.value) * 100))
const priceValid = computed(() => priceGhs.value !== '' && Number.isFinite(priceMinor.value) && priceMinor.value >= 0)
function savePrice() {
  if (!priceValid.value) return
  return update('price', { base_price_ghs: priceMinor.value }, 'Price saved')
}

// Deleting removes the product outright, so it asks first.
const confirmingDelete = ref(false)
async function deleteProduct() {
  if (!product.value) return
  saving.value = 'delete'
  try {
    await adminFetch(`/admin/products/${product.value.id}`, { method: 'DELETE' })
    toast.success('Product deleted', product.value.name)
    await navigateTo('/products')
  } catch (err) {
    const message = failureMessage(err, 'Not deleted. Try again in a moment.')
    if (message) toast.error('Not deleted', message)
    confirmingDelete.value = false
  } finally {
    saving.value = null
  }
}

// --- Shipping and customs ------------------------------------------------
// Grams and millimetres, as stored. International checkout refuses a product
// missing weight, size or HS code while DHL is switched on.
const shipping = reactive({
  weightGrams: '' as string | number,
  lengthMm: '' as string | number,
  widthMm: '' as string | number,
  heightMm: '' as string | number,
  hsCode: '',
  originCountry: 'GH',
  customsDescription: '',
})
watchEffect(() => {
  const s = product.value?.shipping
  if (!s) return
  Object.assign(shipping, {
    weightGrams: s.weightGrams ?? '', lengthMm: s.lengthMm ?? '', widthMm: s.widthMm ?? '', heightMm: s.heightMm ?? '',
    hsCode: s.hsCode ?? '', originCountry: s.originCountry || 'GH', customsDescription: s.customsDescription ?? '',
  })
})
const savingShipping = ref(false)
const shippingError = ref<string | null>(null)
const whole = (v: string | number) => (v === '' || v === null ? null : Math.round(Number(v)))

async function saveShipping() {
  if (!product.value) return
  savingShipping.value = true
  shippingError.value = null
  try {
    await adminFetch(`/admin/products/${product.value.id}`, {
      method: 'PUT',
      body: {
        weight_grams: whole(shipping.weightGrams),
        length_mm: whole(shipping.lengthMm),
        width_mm: whole(shipping.widthMm),
        height_mm: whole(shipping.heightMm),
        hs_code: shipping.hsCode.trim() || null,
        origin_country: shipping.originCountry.trim().toUpperCase() || 'GH',
        customs_description: shipping.customsDescription.trim() || null,
      },
    })
    await refresh()
    toast.success('Shipping details saved')
  } catch (err: unknown) {
    shippingError.value = failureMessage(err)
  } finally {
    savingShipping.value = false
  }
}

// --- Photos -------------------------------------------------------------
// Uploads go to POST /admin/products/{id}/photos, which crops each one to the
// storefront's 9:10 card, converts it to WebP and stores it in R2 as
// catalogue/{slug}/{n}-{colour}-{hash}.webp, the same as the client's
// imported photos. There is no other way to add one.
const photoBusy = ref<string | null>(null)

// Every photo in one list for the viewer, in the order shown on the page.
const viewerImages = computed<LightboxImage[]>(() => {
  const p = product.value
  if (!p) return []
  if (p.colourPhotos?.length) {
    return p.colourPhotos.flatMap((g) => g.urls.map((src, i) => ({
      src,
      alt: `${p.name} in ${g.colour}`,
      caption: `${g.colour} · photo ${i + 1} of ${g.urls.length}`,
    })))
  }
  return (p.imageUrls ?? []).map((src) => ({ src, alt: p.name }))
})
const viewing = ref<number | null>(null)
const openViewer = (src: string) => {
  const i = viewerImages.value.findIndex((img) => img.src === src)
  viewing.value = i === -1 ? null : i
}
const photoError = ref<string | null>(null)

async function addPhotos(event: Event, colour: string | null) {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  input.value = ''
  if (!product.value || !files.length) return
  photoBusy.value = colour ?? ''
  photoError.value = null
  try {
    // One at a time, so they're numbered in the order chosen.
    for (const file of files) {
      const body = new FormData()
      body.append('file', file)
      if (colour) body.append('colour', colour)
      await adminFetch(`/admin/products/${product.value.id}/photos`, { method: 'POST', body })
    }
    await refresh()
    toast.success(files.length === 1 ? 'Photo added' : `${files.length} photos added`)
  } catch (err: unknown) {
    photoError.value = failureMessage(err, 'The photo didn’t upload. Try again in a moment.')
    await refresh()
  } finally {
    photoBusy.value = null
  }
}

async function removePhoto(key: string) {
  if (!product.value) return
  photoBusy.value = key
  photoError.value = null
  try {
    await adminFetch(`/admin/products/${product.value.id}/photos`, { method: 'DELETE', body: { key } })
    await refresh()
    toast.success('Photo removed')
  } catch (err: unknown) {
    photoError.value = failureMessage(err, 'The photo wasn’t removed. Try again in a moment.')
  } finally {
    photoBusy.value = null
  }
}

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
        <template v-if="product && can('products.delete')">
          <UiButton v-if="!confirmingDelete" variant="ghost" size="sm" class="text-danger" @click="confirmingDelete = true">
            <PhTrash :size="16" />
            Delete
          </UiButton>
          <template v-else>
            <UiButton variant="secondary" size="sm" @click="confirmingDelete = false">Keep it</UiButton>
            <UiButton variant="danger" size="sm" :loading="saving === 'delete'" @click="deleteProduct">
              Delete {{ product.name }}
            </UiButton>
          </template>
        </template>
      </template>
    </UiPageHeader>

    <div v-if="!product" class="card">
      <UiEmptyState title="Product not found" />
    </div>

    <div v-else class="grid gap-4 xl:grid-cols-[minmax(0,1fr),320px]">
      <div class="admin-stack min-w-0">
        <SettingsSection title="Details">
          <div class="grid gap-4 sm:grid-cols-2">
            <UiField v-model="details.name" label="Name" required class="sm:col-span-2" :disabled="!can('products.write')" />
            <UiField
              v-model="details.slug" label="Slug" required :disabled="!can('products.write')"
              hint="The end of its web address. Changing it breaks old links."
            />
            <UiSelect v-model="details.categoryId" label="Category" :options="categoryOptions" :disabled="!can('products.write')" />
            <UiField
              v-model="details.productType" label="Type" list="product-types" :disabled="!can('products.write')"
              hint="The shop’s Type filter: slippers, ahenema, closed-toe."
            />
            <datalist id="product-types">
              <option v-for="t in ['slippers', 'ahenema', 'closed-toe']" :key="t" :value="t" />
            </datalist>
            <UiField v-model="details.description" label="Description" type="textarea" class="sm:col-span-2" :disabled="!can('products.write')" />
            <label class="flex items-center gap-2 text-ui text-fg">
              <input v-model="details.isActive" type="checkbox" class="size-4 rounded-sm" :disabled="!can('products.write')">
              On sale in the shop
            </label>
            <label class="flex items-center gap-2 text-ui text-fg">
              <input v-model="details.isFeatured" type="checkbox" class="size-4 rounded-sm" :disabled="!can('products.write')">
              Featured on the home page
            </label>
          </div>
          <p v-if="detailsError" class="mt-3 text-meta text-danger" role="alert">{{ detailsError }}</p>
          <template #footer>
            <UiPermissionGate capability="products.write" quiet>
              <UiButton size="sm" :loading="saving === 'details'" :disabled="!details.name.trim() || !details.slug.trim()" @click="saveDetails">Save</UiButton>
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
                {{ derivedUsd ? formatMoney(derivedUsd) : '-' }}
              </p>
              <p class="mt-1 text-meta text-fg-faint">
                Read-only. Moves with the rate; locked onto each order at checkout.
              </p>
            </div>
          </div>
          <p v-if="priceError" class="mt-3 text-meta text-danger" role="alert">{{ priceError }}</p>
          <template #footer>
            <UiPermissionGate capability="pricing.write">
              <UiButton size="sm" :loading="saving === 'price'" :disabled="!priceValid" @click="savePrice">Save price</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection
          title="Shipping and customs"
          description="The packed box, as a courier weighs and measures it. International orders need all of these and an HS code."
        >
          <div class="mb-3">
            <UiBadge :tone="product.shipping?.readyForInternational ? 'success' : 'warning'" dot>
              {{ product.shipping?.readyForInternational ? 'Ready to ship abroad' : 'Can’t ship abroad yet' }}
            </UiBadge>
          </div>
          <div class="grid gap-4 sm:grid-cols-4">
            <div>
              <label class="field-label" for="ship-weight">Weight (g)</label>
              <input id="ship-weight" v-model="shipping.weightGrams" type="number" min="1" class="field w-full" :disabled="!can('products.write')">
            </div>
            <div>
              <label class="field-label" for="ship-length">Length (mm)</label>
              <input id="ship-length" v-model="shipping.lengthMm" type="number" min="1" class="field w-full" :disabled="!can('products.write')">
            </div>
            <div>
              <label class="field-label" for="ship-width">Width (mm)</label>
              <input id="ship-width" v-model="shipping.widthMm" type="number" min="1" class="field w-full" :disabled="!can('products.write')">
            </div>
            <div>
              <label class="field-label" for="ship-height">Height (mm)</label>
              <input id="ship-height" v-model="shipping.heightMm" type="number" min="1" class="field w-full" :disabled="!can('products.write')">
            </div>
            <div class="sm:col-span-2">
              <label class="field-label" for="ship-hs">HS code</label>
              <input id="ship-hs" v-model="shipping.hsCode" type="text" placeholder="e.g. 6403.99" class="field w-full" :disabled="!can('products.write')">
            </div>
            <div class="sm:col-span-2">
              <label class="field-label" for="ship-origin">Made in (country code)</label>
              <input id="ship-origin" v-model="shipping.originCountry" type="text" maxlength="2" class="field w-full uppercase" :disabled="!can('products.write')">
            </div>
            <div class="sm:col-span-4">
              <label class="field-label" for="ship-customs">Customs description</label>
              <input id="ship-customs" v-model="shipping.customsDescription" type="text" maxlength="255" placeholder="e.g. Leather sandals" class="field w-full" :disabled="!can('products.write')">
              <p class="mt-1.5 text-meta text-fg-faint">What the goods are, plainly, for the customs declaration. Not the product name.</p>
            </div>
          </div>
          <p v-if="shippingError" class="mt-3 rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">
            {{ shippingError }}
          </p>
          <template #footer>
            <UiPermissionGate capability="products.write" quiet>
              <UiButton size="sm" :loading="savingShipping" @click="saveShipping">Save shipping details</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection
          title="Photos"
          :description="product.colourPhotos?.length ? 'Each colourway’s photos, as the storefront shows them.' : undefined"
        >
          <div v-if="product.colourPhotos?.length" class="flex flex-col gap-5">
            <div v-for="group in product.colourPhotos" :key="group.colour" class="flex flex-col gap-2">
              <p class="flex items-center gap-2 text-ui text-fg-strong">
                <span
                  class="size-3.5 shrink-0 rounded-full border border-border"
                  :style="{ backgroundColor: group.hex ?? 'transparent' }"
                />
                {{ group.colour }}
                <span class="text-meta text-fg-faint">
                  {{ group.urls.length }} photo{{ group.urls.length === 1 ? '' : 's' }}
                </span>
              </p>
              <div class="flex flex-wrap gap-2">
                <div v-for="(url, i) in group.urls" :key="url" class="relative">
                  <button
                    type="button"
                    class="block overflow-hidden rounded-lg border border-border hover:border-border-strong"
                    :aria-label="`View ${group.colour} photo ${i + 1}`"
                    @click="openViewer(url)"
                  >
                    <img :src="url" :alt="`${product.name} in ${group.colour}`" loading="lazy" class="h-28 w-[100px] object-cover">
                  </button>
                  <button
                    v-if="can('products.write') && group.keys?.[i]"
                    type="button"
                    class="absolute right-1 top-1 rounded-full bg-bg/90 p-1 text-fg-muted shadow hover:text-danger disabled:opacity-50"
                    :aria-label="`Remove this ${group.colour} photo`"
                    :disabled="photoBusy !== null"
                    @click="removePhoto(group.keys[i])"
                  >
                    <PhX :size="12" />
                  </button>
                </div>
                <label
                  v-if="can('products.write')"
                  class="flex h-28 w-[100px] cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-border text-meta text-fg-muted hover:border-border-strong"
                  :class="{ 'pointer-events-none opacity-50': photoBusy !== null }"
                >
                  <PhPlus :size="16" />
                  {{ photoBusy === group.colour ? 'Uploading…' : 'Add photo' }}
                  <input
                    type="file" accept="image/jpeg,image/png,image/webp,image/avif" multiple class="sr-only"
                    :disabled="photoBusy !== null"
                    @change="addPhotos($event, group.colour)"
                  >
                </label>
              </div>
              <p v-if="!group.urls.length" class="text-meta text-warning">No photo of this colourway yet.</p>
            </div>
          </div>

          <div v-else-if="product.imageUrls?.length" class="flex flex-wrap gap-2">
            <button
              v-for="(url, i) in product.imageUrls" :key="url" type="button"
              class="block overflow-hidden rounded-lg border border-border hover:border-border-strong"
              :aria-label="`View photo ${i + 1}`"
              @click="openViewer(url)"
            >
              <img :src="url" :alt="product.name" loading="lazy" class="h-28 w-[100px] object-cover">
            </button>
          </div>

          <p v-else class="text-ui text-fg-muted">This product has no photos.</p>

          <!-- A product with no colourways takes photos without one. -->
          <label
            v-if="!product.colourPhotos?.length && can('products.write')"
            class="mt-3 inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-dashed border-border px-3 py-2 text-meta text-fg-muted hover:border-border-strong"
            :class="{ 'pointer-events-none opacity-50': photoBusy !== null }"
          >
            <PhPlus :size="14" />
            {{ photoBusy === '' ? 'Uploading…' : 'Add photos' }}
            <input
              type="file" accept="image/jpeg,image/png,image/webp,image/avif" multiple class="sr-only"
              :disabled="photoBusy !== null"
              @change="addPhotos($event, null)"
            >
          </label>

          <p v-if="photoError" class="mt-3 rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">
            {{ photoError }}
          </p>
          <p class="mt-4 text-meta text-fg-faint">
            Photos are cropped to the shop’s 9:10 card and converted to WebP. The first photo is the one on the shop grid.
          </p>

          <UiLightbox v-model:index="viewing" :images="viewerImages" />
        </SettingsSection>

        <SettingsSection
          title="Sizes & colours"
          description="Every colour comes in every size. Each pair is a line of stock below."
        >
          <ProductsVariantsEditor
            v-model:sizes="variantSizes" v-model:colours="variantColours"
            :disabled="!can('products.write')"
          />
          <div v-if="stockWarning" class="mt-3 rounded-lg bg-warning-soft px-3 py-2.5 text-meta text-warning" role="alert">
            <p>{{ stockWarning }}</p>
            <div class="mt-2 flex gap-2">
              <UiButton variant="danger" size="sm" :loading="savingVariants" @click="saveVariants(true)">Remove them and their stock</UiButton>
              <UiButton variant="secondary" size="sm" @click="stockWarning = null; resetVariants()">Undo my changes</UiButton>
            </div>
          </div>
          <p v-if="variantsError" class="mt-3 text-meta text-danger" role="alert">{{ variantsError }}</p>
          <template #footer>
            <UiPermissionGate capability="products.write" quiet>
              <UiButton v-if="variantsChanged" variant="ghost" size="sm" @click="resetVariants">Undo</UiButton>
              <UiButton size="sm" :loading="savingVariants && !stockWarning" :disabled="!variantsChanged || !variantSizes.length" @click="saveVariants()">
                Save sizes & colours
              </UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection title="Variants" description="Stock per size and colourway. Counts are set in Inventory.">
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
