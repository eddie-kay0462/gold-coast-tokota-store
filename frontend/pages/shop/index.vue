<script setup lang="ts">
import { PhFunnelSimple as FunnelSimple, PhX as X } from '@phosphor-icons/vue'
import type { ApiProduct, ColorFacet, Facet } from '~/utils/catalog'
import { COLOR_FACETS, TYPE_FACETS } from '~/utils/catalog'

const apiFetch = useApiFetch()
const route = useRoute()
const router = useRouter()

// Sidebar facets. `category` is deliberately NOT one of them: `?category=` is
// the *department* (mens/womens/kids/merchandise), while the sidebar's
// "Category" group filters product type. Sharing one key would make a link from
// elsewhere filter every product out.
//
// The header no longer emits `?category=`; its nav moved to `?type=` with the
// Template B labels on 27 Aug, because departments only exist on the design
// catalogue and the API has never returned them. The search panel still links
// two department views, and the key still works.
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

// A failed request is a "try again" page, never the old design catalogue
// standing in: that showed demo stock and prices whenever the API was busy.
// An empty result is just an empty result, and the grid says so.
//
// 48 a page, the API's cap: the catalogue (40 today) fits on one, and "Load
// more" appends the next page when it outgrows it. Before this the page asked
// for the API's default 12 and stopped, so most of the catalogue never showed.
const PAGE_SIZE = 48
type ProductPage = { data: ApiProduct[], meta?: { total?: number, last_page?: number } }

const { data: apiProducts, pending, error: productsError } = await useAsyncData(
  'shop-products',
  () =>
    apiFetch<ProductPage>('/products', {
      query: {
        per_page: PAGE_SIZE,
        ...Object.fromEntries(
          FACET_KEYS.map((key) => [key, selected.value[key].join(',') || undefined]),
        ),
        category: route.query.category,
        q: route.query.q,
        sale: route.query.sale,
        sort: route.query.sort,
      },
    }),
  { watch: [() => route.query] },
)

if (productsError.value) throw apiUnavailable()
// A filter change that fails in the browser gets the same page, not an empty grid.
watch(productsError, (error) => { if (error) showError(apiUnavailable()) })

/** Pages after the first, appended by "Load more"; cleared when the filters change. */
const morePages = ref<ApiProduct[]>([])
const nextPage = ref(2)
const loadingMore = ref(false)
watch(() => route.query, () => {
  morePages.value = []
  nextPage.value = 2
})

const lastPage = computed(() => apiProducts.value?.meta?.last_page ?? 1)
const hasMore = computed(() => nextPage.value <= lastPage.value)

async function loadMore() {
  if (loadingMore.value) return
  loadingMore.value = true
  try {
    const res = await apiFetch<ProductPage>('/products', {
      query: { ...route.query, per_page: PAGE_SIZE, page: nextPage.value },
    })
    morePages.value = [...morePages.value, ...res.data]
    nextPage.value += 1
  } catch {
    // The button stays; the next tap tries again.
  } finally {
    loadingMore.value = false
  }
}

/**
 * The sidebar's options, from the catalogue (`GET /products/facets`): only
 * the types and colours products actually have, with the swatch each colour
 * is entered with. The built-in lists stand in if the API can't be reached.
 */
const { data: facets } = await useAsyncData(
  'product-facets',
  () => apiFetch<{ data: { types: (Facet & { count: number })[], colors: ColorFacet[] } }>('/products/facets')
    .then((res) => res.data)
    .catch(() => null),
)
const typeFacets = computed<Facet[]>(() => facets.value?.types.length ? facets.value.types : TYPE_FACETS)
const colorFacets = computed<ColorFacet[]>(() => facets.value?.colors.length ? facets.value.colors : COLOR_FACETS)

const sourceProducts = computed(() => [...(apiProducts.value?.data ?? []), ...morePages.value])

/** Free-text term from the header search panel. */
const searchTerm = computed(() =>
  typeof route.query.q === 'string' ? route.query.q.trim().toLowerCase() : '',
)

// The API filters server-side.
const products = computed(() => sourceProducts.value)

/** Everything the filters match, not just what's loaded so far. */
const resultCount = computed(() => apiProducts.value?.meta?.total ?? products.value.length)

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
  if (searchTerm.value) return `Search results for “${route.query.q}”`
  if (route.query.sale === 'true') return 'Sale'
  const base = activeCategory.value?.heading ?? 'All Products'
  return route.query.sort === 'newest' ? `${base} - New Arrivals` : base
})

const breadcrumb = computed(() =>
  searchTerm.value ? 'Home / Search' : `Home / ${activeCategory.value?.crumb ?? 'Shop'}`,
)

const subheading = computed(() => {
  if (searchTerm.value) return `${resultCount.value} matching`
  if (route.query.sort === 'best-selling') return 'Best Sellers'
  if (route.query.sort === 'top-rated') return 'Top Rated'
  return 'Featured'
})

const filtersOpen = ref(false)

/** Shown on the toggle so the count of active filters is visible from the grid. */
const activeFacetCount = computed(() =>
  FACET_KEYS.reduce((total, key) => total + selected.value[key].length, 0),
)

// The sheet is an overlay on a phone, so the grid behind it must not scroll.
useBodyScrollLock(filtersOpen)

// Widening past `md` turns the sheet back into a permanent sidebar; leaving the
// flag set would keep the scrim and the scroll lock on a layout that has neither.
onMounted(() => {
  const desktop = window.matchMedia('(min-width: 768px)')
  const sync = () => {
    if (desktop.matches) filtersOpen.value = false
  }
  desktop.addEventListener('change', sync)
  onBeforeUnmount(() => desktop.removeEventListener('change', sync))
})

useSeoMeta({
  title: `${heading.value} - Gold Coast Tokota`,
  description:
    'Browse handmade Ghanaian sandals, slippers and shoes. Filter by category, colour, size and width.',
  ogTitle: `${heading.value} - Gold Coast Tokota`,
  ogImage: '/brand/og-image.png',
})
</script>

<template>
  <div class="page-gutter mx-auto flex w-full max-w-[1560px] flex-col items-start gap-4 pb-16 pt-[30px] md:flex-row md:gap-6 lg:pb-[90px]">
    <!-- Below `md` the filters are a sheet, not an inline accordion. Expanded
         inline they pushed ~1000px of panel above the first product, with no
         way back to the grid but scrolling up to the toggle. -->
    <button
      type="button"
      class="flex min-h-[44px] w-full items-center justify-between border-b border-line py-4 text-caption text-graphite md:hidden"
      :aria-expanded="filtersOpen"
      aria-controls="shop-filters"
      @click="filtersOpen = true"
    >
      <span class="flex items-center gap-2">
        Filters
        <span
          v-if="activeFacetCount"
          class="flex size-5 items-center justify-center rounded-full bg-graphite text-tag text-white"
        >{{ activeFacetCount }}</span>
      </span>
      <FunnelSimple :size="16" />
    </button>

    <!-- Scrim, phone only. -->
    <Transition
      enter-active-class="motion-safe:transition-opacity motion-safe:duration-200"
      leave-active-class="motion-safe:transition-opacity motion-safe:duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="filtersOpen"
        class="fixed inset-0 z-[60] bg-black/60 md:hidden"
        aria-hidden="true"
        @click="filtersOpen = false"
      />
    </Transition>

    <div
      id="shop-filters"
      class="w-full md:block md:w-auto"
      :class="filtersOpen
        ? 'fixed inset-y-0 left-0 z-[70] flex max-w-[90vw] flex-col bg-white md:static md:z-auto md:max-w-none md:bg-transparent'
        : 'hidden'"
    >
      <div
        v-if="filtersOpen"
        class="flex shrink-0 items-center justify-between border-b border-line px-5 py-3 md:hidden"
      >
        <h2 class="text-filter-heading font-normal text-graphite">Filters</h2>
        <button
          type="button"
          class="-m-1.5 flex size-11 items-center justify-center text-graphite"
          aria-label="Close filters"
          @click="filtersOpen = false"
        >
          <X :size="20" />
        </button>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto" :class="filtersOpen ? 'px-5 py-4 md:p-0' : ''">
        <ShopProductFilter
          :product-count="resultCount"
          :selected="selected"
          :types="typeFacets"
          :colors="colorFacets"
          @toggle="toggleFacet"
        />
      </div>

      <div v-if="filtersOpen" class="shrink-0 border-t border-line p-4 md:hidden">
        <CommonBrandButton full @click="filtersOpen = false">
          Show {{ resultCount }} result{{ resultCount === 1 ? '' : 's' }}
        </CommonBrandButton>
      </div>
    </div>

    <div class="flex min-w-0 flex-1 flex-col">
      <div class="flex w-full flex-col pb-2 pt-4 font-light">
        <p class="text-caption text-muted">{{ breadcrumb }}</p>
        <h1 class="text-display-md text-black">{{ heading }}</h1>
        <p class="text-body text-black">{{ subheading }}</p>
      </div>

      <ShopProductGrid :products="products" :pending="pending" />

      <div v-if="hasMore" class="flex w-full flex-col items-center gap-2 pt-10">
        <p class="text-caption text-muted">Showing {{ products.length }} of {{ resultCount }}</p>
        <CommonBrandButton :disabled="loadingMore" @click="loadMore">
          {{ loadingMore ? 'Loading…' : 'Load more' }}
        </CommonBrandButton>
      </div>
    </div>
  </div>
</template>
