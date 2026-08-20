<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'
import { DESIGN_POSTS, SUSTAINABILITY_POSTS } from '~/utils/newsPosts'

/**
 * "The people, stories, and ideas that will help us get there" — the listing
 * carries the programme categories rather than the whole news feed.
 */
const PROGRAMME_CATEGORIES = ['Sustainability', 'Careers', 'Partnerships', 'Advocacy', 'Processes', 'Style', 'Community']

const config = useRuntimeConfig()

// Feature 9 owns the blog CMS. A missing or failing endpoint resolves to an
// empty list rather than throwing, and the designed stories stand in — the
// same fallback pattern the home, about, shop and blog pages use.
const { data: posts } = await useAsyncData('sustainability-posts', () =>
  $fetch<{ data: ApiPost[] }>(`${config.public.apiBase}/blog-posts`)
    .catch(() => ({ data: [] as ApiPost[] })),
)

const items = computed(() => {
  const fromApi = posts.value?.data ?? []

  // Until the CMS exists the six designed stories lead, followed by the news
  // posts that belong to the same programme — enough to make "Load More"
  // meaningful rather than decorative.
  const pool = fromApi.length
    ? [...fromApi].sort((a, b) => b.published_at.localeCompare(a.published_at))
    : [...SUSTAINABILITY_POSTS, ...DESIGN_POSTS]

  const seen = new Set<string>()
  return pool.filter((post) => {
    if (seen.has(post.slug)) return false
    if (!PROGRAMME_CATEGORIES.includes(post.category ?? '')) return false
    seen.add(post.slug)
    return true
  })
})

useSeoMeta({
  title: 'Sustainability — Gold Coast Tokota',
  description:
    'We\'re on a mission to clean up a dirty industry. These are the people, stories, and ideas that will help us get there.',
  ogTitle: 'Sustainability — Gold Coast Tokota',
  ogDescription: 'We\'re on a mission to clean up a dirty industry.',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <SustainabilityHeroSection />

    <MotionGsapScrollReveal>
      <SustainabilityArticleGrid :posts="items" />
    </MotionGsapScrollReveal>

    <SustainabilitySloganTicker />

    <MotionGsapScrollReveal>
      <SustainabilityProgressGrid />
    </MotionGsapScrollReveal>

    <SustainabilitySocialCta />
  </div>
</template>
