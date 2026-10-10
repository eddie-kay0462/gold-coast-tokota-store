<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'
import { DESIGN_POSTS } from '~/utils/newsPosts'

const config = useRuntimeConfig()

// Feature 9 owns the blog CMS. A missing or failing endpoint resolves to an
// empty list rather than throwing, and the design's own posts stand in; the
// same fallback pattern the home and shop pages use.
const { data: posts } = await useAsyncData('blog-posts', () =>
  $fetch<{ data: ApiPost[] }>(`${config.public.apiBase}/blog-posts`)
    .catch(() => ({ data: [] as ApiPost[] })),
)

const allPosts = computed(() => {
  const fromApi = posts.value?.data ?? []
  if (!fromApi.length) return DESIGN_POSTS

  // Newest first, matching how the CMS will order published posts.
  return [...fromApi].sort((a, b) => b.published_at.localeCompare(a.published_at))
})

/**
 * Category filter.
 *
 * The categories are derived from the posts themselves rather than from a fixed
 * allowlist: the CMS owns them, and a hardcoded list would silently hide any
 * category the brand adds later. About's programme strand does use an allowlist,
 * but it is doing something different: selecting a subset of the feed for one
 * section.
 *
 * State lives in the URL so a filtered view is shareable and survives a
 * back-button, the same way `/shop` handles its facets.
 */
const route = useRoute()
const router = useRouter()

const categories = computed(() =>
  [...new Set(allPosts.value.map((post) => post.category).filter((c): c is string => !!c))].sort(),
)

const activeCategory = computed(() => {
  const requested = String(route.query.category ?? '')
  return categories.value.includes(requested) ? requested : ''
})

function setCategory(category: string) {
  router.push({ query: category ? { ...route.query, category } : { ...route.query, category: undefined } })
}

const items = computed(() =>
  activeCategory.value
    ? allPosts.value.filter((post) => post.category === activeCategory.value)
    : allPosts.value,
)

useSeoMeta({
  title: 'Stories | Gold Coast Tokota',
  description:
    'Learn more about our brand, our sustainability journey and upcoming community events.',
  ogTitle: 'Stories | Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="page-gutter mx-auto flex w-full max-w-[1440px] flex-col items-start gap-8 py-12 lg:gap-10 lg:pb-[90px] lg:pt-12">
    <!-- Left-aligned like the home page's section headings, not centred. -->
    <div class="flex w-full flex-col items-start gap-2">
      <h1 class="w-full text-display-md text-ink">Stories</h1>
      <p class="w-full max-w-[560px] text-label text-subtle">
        Learn more about our brand, our sustainability journey and upcoming community events.
      </p>
    </div>

    <!-- Text tabs in the header's category-row style rather than filled chips.
         One row on a hairline, scrolling sideways on a phone rather than
         wrapping into a block of buttons. -->
    <div
      v-if="categories.length > 1"
      class="flex w-full gap-6 overflow-x-auto border-b border-line [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    >
      <button
        v-for="option in [{ value: '', label: 'All stories' }, ...categories.map((c) => ({ value: c, label: c }))]"
        :key="option.value"
        type="button"
        class="caps-label -mb-px flex min-h-[44px] shrink-0 items-center whitespace-nowrap border-b transition-colors"
        :class="activeCategory === option.value
          ? 'border-ink text-ink'
          : 'border-transparent text-subtle hover:text-ink'"
        :aria-pressed="activeCategory === option.value"
        @click="setCategory(option.value)"
      >
        {{ option.label }}
      </button>
    </div>

    <BlogList :posts="items" />
  </div>
</template>
