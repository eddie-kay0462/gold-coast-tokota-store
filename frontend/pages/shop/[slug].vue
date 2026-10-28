<script setup lang="ts">
import type { ApiProduct } from '~/utils/catalog'

const route = useRoute()
const apiFetch = useApiFetch()
const addToCartLine = useAddToCart()

const slug = computed(() => String(route.params.slug))

// Only the API answering 404 makes this a missing product. Any other failure
// (rate limited, down, unreachable) is a "try again" page: never a 404, and
// never the old design catalogue standing in for real stock.
const { data: product, error: productError } = await useAsyncData(
  () => `product-${slug.value}`,
  () =>
    apiFetch<{ data: ApiProduct }>(`/products/${slug.value}`)
      .then((response): ApiProduct | null => response.data)
      .catch((error) => {
        if (isNotFound(error)) return null
        throw error
      }),
  { watch: [slug] },
)

if (productError.value) throw apiUnavailable()
if (!product.value) {
  throw createError({ statusCode: 404, statusMessage: 'Product not found', fatal: true })
}

// The chosen colourway, shared with the purchase panel. Each photograph is a
// different colour of the style, so the gallery shows the chosen colour's
// photos rather than cycling through colours the customer didn't pick.
// Initialised here, not by the panel: the gallery renders before the panel,
// so a server render would otherwise show every colour and then swap.
// A product card links here with `?color=` when a colour was tapped on it.
const defaultColour = () => {
  const asked = typeof route.query.color === 'string' ? route.query.color : ''
  if (asked && product.value?.colors?.some((colour) => colour.name === asked)) return asked
  return product.value?.color ?? product.value?.colors?.[0]?.name ?? ''
}
const selectedColor = ref(defaultColour())
watch(product, () => { selectedColor.value = defaultColour() })

const gallery = computed(() => {
  const entry = product.value
  if (!entry) return []
  const forColour = entry.colour_images?.[selectedColor.value]
  return forColour?.length ? forColour : entry.images ?? []
})

const discountLabel = computed(() => {
  const entry = product.value
  if (!entry?.compare_at_ghs || entry.compare_at_ghs <= entry.base_price_ghs) return null
  return `${Math.round((1 - entry.base_price_ghs / entry.compare_at_ghs) * 100)}% off`
})

/** "Men / Sandals - New Arrivals": department, then product type. */
const DEPARTMENT_LABELS: Record<string, string> = {
  mens: 'Men',
  womens: 'Women',
  kids: 'Kids',
}

const breadcrumb = computed(() => {
  const entry = product.value
  if (!entry) return ''
  const department = DEPARTMENT_LABELS[entry.departments?.[0] ?? ''] ?? 'Shop'
  const type = entry.product_type
    ? entry.product_type.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
    : null
  return type ? `${department} / ${type}` : department
})

/**
 * The stock badge from the approved mockup, shared with the product card so
 * the grid and the detail page never disagree about availability.
 */
const STOCK_BADGES: Record<string, { label: string, class: string }> = {
  limited_stock: { label: 'Limited stock', class: 'bg-gold text-chrome' },
  back_in_stock: { label: 'Back in stock', class: 'bg-chrome text-white' },
  out_of_stock: { label: 'Out of stock', class: 'bg-line text-muted' },
}

// Live while the page is open: a size that sells out is struck through, and the
// gallery's stock badge follows, without a reload.
const { stock: liveStock } = useInventoryPolling(slug)

const stockBadge = computed(() => {
  const badge = liveStock.value ? liveStock.value.merchandising_badge : product.value?.merchandising_badge
  return badge ? STOCK_BADGES[badge] ?? null : null
})

// From the API, ranked for this product (same department first, in stock
// first). If it can't be reached the row is simply left out.
const { data: apiRecommended } = await useAsyncData(
  () => `recommended-${slug.value}`,
  () =>
    apiFetch<{ data: ApiProduct[] }>('/products/recommendations', {
      query: { for: slug.value, limit: 4 },
    })
      .then((response) => response.data)
      .catch(() => [] as ApiProduct[]),
  { watch: [slug] },
)

const recommended = computed(() => apiRecommended.value ?? [])

function addToCart({ size, color }: { size: string, color: string }) {
  if (!product.value) return
  addToCartLine(product.value, { size, color })
}

useSeoMeta({
  title: () => `${product.value?.name ?? 'Product'} | Gold Coast Tokota`,
  description: () =>
    product.value?.description ?? 'Handmade Ghanaian footwear from Gold Coast Tokota.',
  ogTitle: () => product.value?.name,
  ogImage: () => product.value?.images?.[0] ?? '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div v-if="product" class="flex w-full flex-col items-start">
    <!-- Section 01: gallery + buy panel -->
    <div class="page-gutter mx-auto flex w-full max-w-[1168px] flex-col items-start gap-6 py-[30px] md:flex-row">
      <ShopProductGallery
        :images="gallery"
        :name="product.name"
        :discount-label="discountLabel"
        :stock-badge="stockBadge"
      />
      <ShopProductPurchasePanel
        v-model:color="selectedColor"
        :product="product"
        :breadcrumb="breadcrumb"
        :live-stock="liveStock"
        @add="addToCart"
      />
    </div>

    <!-- Section 02: recommendations -->
    <section v-if="recommended.length" class="page-gutter section-y mx-auto flex w-full max-w-[1168px] flex-col items-start gap-2">
      <h2 class="w-full text-body font-normal text-graphite">Recommended Products</h2>
      <ul class="grid w-full grid-cols-2 gap-x-6 gap-y-8 lg:grid-cols-4">
        <li v-for="item in recommended" :key="item.slug" class="min-w-0">
          <ShopProductCard :product="item" />
        </li>
      </ul>
    </section>

    <!-- Section 03: reviews -->
    <ShopProductReviews
      v-if="product.rating && product.reviews?.length"
      :rating="product.rating"
      :reviews="product.reviews"
    />

    <!-- Section 04: transparent pricing -->
    <ShopTransparentPricing
      v-if="product.cost_breakdown?.length"
      :breakdown="product.cost_breakdown"
    />
  </div>
</template>
