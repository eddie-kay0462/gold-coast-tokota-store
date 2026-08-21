<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'
import { DESIGN_POSTS } from '~/utils/newsPosts'

const config = useRuntimeConfig()

// Feature 9 owns the blog CMS. A missing or failing endpoint resolves to an
// empty list rather than throwing, and the design's own posts stand in — the
// same fallback pattern the home and shop pages use.
const { data: posts } = await useAsyncData('blog-posts', () =>
  $fetch<{ data: ApiPost[] }>(`${config.public.apiBase}/blog-posts`)
    .catch(() => ({ data: [] as ApiPost[] })),
)

const items = computed(() => {
  const fromApi = posts.value?.data ?? []
  if (!fromApi.length) return DESIGN_POSTS

  // Newest first, matching how the CMS will order published posts.
  return [...fromApi].sort((a, b) => b.published_at.localeCompare(a.published_at))
})

useSeoMeta({
  title: 'News & Events — Gold Coast Tokota',
  description:
    'Learn more about our brand, our sustainability journey and upcoming community events.',
  ogTitle: 'News & Events — Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="page-gutter flex w-full flex-col items-center gap-12 py-12 lg:gap-16 lg:pb-[90px] lg:pt-[30px]">
    <div class="flex w-full flex-col items-center gap-4 text-center font-light text-black">
      <h1 class="w-full text-display-md">News &amp; Events</h1>
      <p class="w-full text-body">
        Learn more about our brand, our sustainability journey and upcoming community events
      </p>
    </div>

    <BlogList :posts="items" />
  </div>
</template>
