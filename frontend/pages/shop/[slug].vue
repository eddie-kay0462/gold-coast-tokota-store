<script setup lang="ts">
import type { ApiProduct } from '~/utils/catalog'
import { DESIGN_PRODUCTS } from '~/utils/designCatalogue'

const route = useRoute()
const config = useRuntimeConfig()
const addToCartLine = useAddToCart()

const slug = computed(() => String(route.params.slug))

// Feature 2 owns `GET /api/v1/products/{slug}`. Until it exists a failed fetch
// resolves to null and the design catalogue stands in, matching how the listing
// and home pages already degrade.
const { data: apiProduct } = await useAsyncData(
  () => `product-${slug.value}`,
  () =>
    $fetch<{ data: ApiProduct }>(`${config.public.apiBase}/products/${slug.value}`)
      .then((response) => response.data)
      .catch(() => null),
  { watch: [slug] },
)

const product = computed<ApiProduct | null>(
  () => apiProduct.value ?? DESIGN_PRODUCTS.find((entry) => entry.slug === slug.value) ?? null,
)

// A slug that matches neither the API nor the design catalogue is a genuine 404
// rather than an empty product page.
if (!product.value) {
  throw createError({ statusCode: 404, statusMessage: 'Product not found', fatal: true })
}

const gallery = computed(() => product.value?.images ?? [])

const discountLabel = computed(() => {
  const entry = product.value
  if (!entry?.compare_at_ghs || entry.compare_at_ghs <= entry.base_price_ghs) return null
  return `${Math.round((1 - entry.base_price_ghs / entry.compare_at_ghs) * 100)}% off`
})

/** "Men / Sandals - New Arrivals" — department, then product type. */
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

const stockBadge = computed(() => {
  const badge = product.value?.merchandising_badge
  return badge ? STOCK_BADGES[badge] ?? null : null
})

const recommended = computed(() =>
  DESIGN_PRODUCTS.filter((entry) => entry.slug !== slug.value).slice(0, 4),
)

function addToCart({ size, color }: { size: string, color: string }) {
  if (!product.value) return
  addToCartLine(product.value, { size, color })
}

useSeoMeta({
  title: () => `${product.value?.name ?? 'Product'} — Gold Coast Tokota`,
  description: () =>
    product.value?.description ?? 'Handmade Ghanaian footwear from Gold Coast Tokota.',
  ogTitle: () => product.value?.name,
  ogImage: () => product.value?.images?.[0] ?? '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div v-if="product" class="flex w-full flex-col items-start">
    <!-- Section 01 — gallery + buy panel -->
    <div class="page-gutter mx-auto flex w-full max-w-[1168px] flex-col items-start gap-6 py-[30px] md:flex-row">
      <ShopProductGallery
        :images="gallery"
        :name="product.name"
        :discount-label="discountLabel"
        :stock-badge="stockBadge"
      />
      <ShopProductPurchasePanel
        :product="product"
        :breadcrumb="breadcrumb"
        @add="addToCart"
      />
    </div>

    <!-- Section 02 — recommendations -->
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1168px] flex-col items-start gap-2">
      <h2 class="w-full text-body font-normal text-graphite">Recommended Products</h2>
      <ul class="grid w-full grid-cols-2 gap-x-6 gap-y-8 lg:grid-cols-4">
        <li v-for="item in recommended" :key="item.slug" class="min-w-0">
          <ShopProductCard :product="item" />
        </li>
      </ul>
    </section>

    <!-- Section 03 — reviews -->
    <ShopProductReviews
      v-if="product.rating && product.reviews?.length"
      :rating="product.rating"
      :reviews="product.reviews"
    />

    <!-- Section 04 — transparent pricing -->
    <ShopTransparentPricing
      v-if="product.cost_breakdown?.length"
      :breakdown="product.cost_breakdown"
    />
  </div>
</template>
