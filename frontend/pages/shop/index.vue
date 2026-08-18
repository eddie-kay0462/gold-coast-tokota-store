<script setup lang="ts">
import { PhFunnelSimple as FunnelSimple } from '@phosphor-icons/vue'
import type { ApiProduct } from '~/utils/catalog'
import { COLOR_FACETS } from '~/utils/catalog'

const config = useRuntimeConfig()
const route = useRoute()
const router = useRouter()

// Sidebar facets. `category` is deliberately NOT one of them: the header's
// category nav uses `?category=mens` for the *department*, while the sidebar's
// "Category" group filters product type. Sharing one key would make a link from
// the nav filter every product out.
type FacetKey = 'type' | 'color' | 'size' | 'width'
const FACET_KEYS: FacetKey[] = ['type', 'color', 'size', 'width']

/** Facets live in the URL so a filtered listing is linkable and SSR-renderable. */
function readFacet(key: FacetKey): string[] {
  const raw = route.query[key]
  const value = Array.isArray(raw) ? raw.join(',') : raw
  return typeof value === 'string' && value ? value.split(',').filter(Boolean) : []
}

const selected = computed(
  () =>
    Object.fromEntries(FACET_KEYS.map((key) => [key, readFacet(key)])) as Record<
      FacetKey,
      string[]
    >,
)

function toggleFacet(facet: FacetKey, value: string) {
  const current = selected.value[facet]
  const next = current.includes(value)
    ? current.filter((entry) => entry !== value)
    : [...current, value]

  router.replace({
    query: { ...route.query, [facet]: next.length ? next.join(',') : undefined },
  })
}

// The catalogue endpoint arrives with Feature 2. Until then a failed/absent
// endpoint resolves to an empty list rather than throwing, and the design's own
// six products stand in — the same fallback pattern the home page uses.
const { data: apiProducts, pending } = await useAsyncData(
  'shop-products',
  () =>
    $fetch<{ data: ApiProduct[] }>(`${config.public.apiBase}/products`, {
      query: {
        ...Object.fromEntries(
          FACET_KEYS.map((key) => [key, selected.value[key].join(',') || undefined]),
        ),
        category: route.query.category,
        sale: route.query.sale,
        sort: route.query.sort,
      },
    }).catch(() => ({ data: [] as ApiProduct[] })),
  { watch: [() => route.query] },
)

const designFallback: ApiProduct[] = [
  {
    slug: 'kentehene-collection',
    name: 'The Kentehene Collection',
    base_price_ghs: 60000,
    compare_at_ghs: 70000,
    images: ['/design/product-kentehene.png'],
    color: 'Brown',
    colors: [
      { name: 'Brown', hex: '#8B5A2B' },
      { name: 'Navy', hex: '#1B2A4A' },
      { name: 'Tan', hex: '#C4AE93' },
      { name: 'Black', hex: '#000000' },
    ],
    product_type: 'ahenema',
    departments: ['mens'],
    sizes: ['39', '40', '41', '42', '43'],
    widths: ['m', 'l'],
  },
  {
    slug: 'acheampong-collection',
    name: 'The Acheampong Collection',
    base_price_ghs: 70000,
    compare_at_ghs: 75000,
    images: ['/design/product-acheampong.png'],
    color: 'Navy Blue',
    colors: [
      { name: 'Navy Blue', hex: '#1B2A4A' },
      { name: 'Black', hex: '#000000' },
      { name: 'Grey', hex: '#D9D9D9' },
    ],
    tags: ['Custom Made'],
    product_type: 'slippers',
    departments: ['mens'],
    sizes: ['40', '41', '42', '43', '44'],
    widths: ['m'],
  },
  {
    slug: 'adinkra-slippers',
    name: 'The Adinkra Slippers',
    base_price_ghs: 45000,
    compare_at_ghs: 55000,
    images: ['/design/product-adinkra.png'],
    color: 'Black and White',
    colors: [
      { name: 'White', hex: '#FFFFFF' },
      { name: 'Olive', hex: '#5A6134' },
    ],
    tags: ['Renewed Materials', 'Cleaner Chemistry'],
    product_type: 'slippers',
    departments: ['mens', 'womens'],
    sizes: ['38', '39', '40', '41', '42'],
    widths: ['s', 'm'],
  },
  {
    slug: 'elevated-odeneho',
    name: 'The Elevated Odeneho',
    base_price_ghs: 50000,
    compare_at_ghs: 60000,
    images: ['/design/product-elevated-odeneho.png'],
    color: 'Brown',
    colors: [
      { name: 'Brown', hex: '#8B5A2B' },
      { name: 'Navy', hex: '#1B2A4A' },
      { name: 'Tan', hex: '#C4AE93' },
      { name: 'Black', hex: '#000000' },
    ],
    product_type: 'sandals',
    departments: ['mens'],
    sizes: ['41', '42', '43', '44', '45'],
    widths: ['m', 'l'],
  },
  {
    slug: 'flavourful-cross-slippers',
    name: 'Flavourful Cross Slippers',
    base_price_ghs: 45000,
    compare_at_ghs: 50000,
    images: ['/design/product-flavourful-cross.png'],
    color: 'Crystal Blue',
    colors: [
      { name: 'Crystal Blue', hex: '#1E4C8F' },
      { name: 'Black', hex: '#000000' },
      { name: 'Grey', hex: '#D9D9D9' },
    ],
    tags: ['Organic Materials'],
    product_type: 'slippers',
    departments: ['mens', 'womens'],
    sizes: ['38', '39', '40', '41'],
    widths: ['s', 'm'],
  },
  {
    slug: 'odeneho-collection',
    name: 'The Odeneho Collection',
    base_price_ghs: 25000,
    compare_at_ghs: 30000,
    images: ['/design/product-odeneho.png'],
    color: 'Olive Green',
    colors: [{ name: 'Olive Green', hex: '#5A6134' }],
    tags: ['Renewed Materials', 'Cleaner Chemistry'],
    is_pre_order: true,
    product_type: 'closed-toe',
    departments: ['mens'],
    sizes: ['42', '43', '44', '45'],
    widths: ['m'],
  },
]

const usingFallback = computed(() => !(apiProducts.value?.data?.length))
const sourceProducts = computed(() => apiProducts.value?.data ?? [])

/** Maps a product's colourway names onto the sidebar's colour facet values. */
function colorValues(product: ApiProduct): string[] {
  const names = [product.color, ...(product.colors ?? []).map((color) => color.name)]
  return COLOR_FACETS.filter((facet) =>
    names.some((name) => name?.toLowerCase().includes(facet.label.toLowerCase())),
  ).map((facet) => facet.value)
}

function matchesFilters(product: ApiProduct): boolean {
  const { type, color, size, width } = selected.value
  const department = route.query.category

  if (
    typeof department === 'string'
    && department
    && !(product.departments ?? []).includes(department)
  ) return false
  if (route.query.sale === 'true' && !(product.compare_at_ghs
    && product.compare_at_ghs > product.base_price_ghs)) return false
  if (type.length && !(product.product_type && type.includes(product.product_type))) return false
  if (color.length && !colorValues(product).some((value) => color.includes(value))) return false
  if (size.length && !(product.sizes ?? []).some((entry) => size.includes(entry))) return false
  if (width.length && !(product.widths ?? []).some((entry) => width.includes(entry))) return false
  return true
}

// The API is expected to filter server-side; the same predicate runs locally so
// the fallback catalogue responds to the sidebar too.
const products = computed(() =>
  usingFallback.value ? designFallback.filter(matchesFilters) : sourceProducts.value,
)

/** Heading and breadcrumb follow the category the header nav linked in with. */
const CATEGORY_LABELS: Record<string, { crumb: string, heading: string }> = {
  mens: { crumb: 'Men', heading: 'Men’s Sandals' },
  womens: { crumb: 'Women', heading: 'Women’s Sandals' },
  kids: { crumb: 'Kids', heading: 'Kids’ Sandals' },
  merchandise: { crumb: 'Merchandise', heading: 'Merchandise' },
}

const activeCategory = computed(() => {
  const value = route.query.category
  return typeof value === 'string' ? CATEGORY_LABELS[value] : undefined
})

const heading = computed(() => {
  if (route.query.sale === 'true') return 'Sale'
  const base = activeCategory.value?.heading ?? 'All Products'
  return route.query.sort === 'newest' ? `${base} - New Arrivals` : base
})

const breadcrumb = computed(() => `Home / ${activeCategory.value?.crumb ?? 'Shop'}`)

const subheading = computed(() => {
  if (route.query.sort === 'best-selling') return 'Best Sellers'
  if (route.query.sort === 'top-rated') return 'Top Rated'
  return 'Featured'
})

const filtersOpen = ref(false)

useSeoMeta({
  title: `${heading.value} — Gold Coast Tokota`,
  description:
    'Browse handmade Ghanaian sandals, slippers and shoes — filter by category, colour, size and width.',
  ogTitle: `${heading.value} — Gold Coast Tokota`,
  ogImage: '/brand/og-image.png',
})
</script>

<template>
  <div class="flex w-full flex-col items-start gap-4 px-5 py-[30px] lg:flex-row lg:px-20">
    <!-- Mobile: the sidebar collapses behind a toggle rather than pushing the
         grid a full screen down. -->
    <button
      type="button"
      class="flex w-full items-center justify-between border-b border-line py-4 text-caption text-graphite lg:hidden"
      :aria-expanded="filtersOpen"
      aria-controls="shop-filters"
      @click="filtersOpen = !filtersOpen"
    >
      <span>Filters</span>
      <FunnelSimple :size="16" />
    </button>

    <div id="shop-filters" class="w-full lg:w-auto" :class="filtersOpen ? '' : 'hidden lg:block'">
      <ShopProductFilter
        :product-count="products.length"
        :selected="selected"
        @toggle="toggleFacet"
      />
    </div>

    <div class="flex min-w-0 flex-1 flex-col">
      <div class="flex w-full flex-col pb-2 pt-4 font-light">
        <p class="text-caption text-muted">{{ breadcrumb }}</p>
        <h1 class="text-display-md text-black">{{ heading }}</h1>
        <p class="text-body text-black">{{ subheading }}</p>
      </div>

      <ShopProductGrid :products="products" :pending="pending" />
    </div>
  </div>
</template>
