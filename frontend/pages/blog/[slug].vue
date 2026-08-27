<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'
import { DESIGN_POSTS, SUSTAINABILITY_POSTS } from '~/utils/newsPosts'

const route = useRoute()
const config = useRuntimeConfig()

const slug = computed(() => String(route.params.slug))

// Feature 9 owns `GET /api/v1/blog-posts/{slug}`. Until it lands a failed fetch
// resolves to null and the design's posts stand in.
const { data: apiPost } = await useAsyncData(
  () => `blog-${slug.value}`,
  () =>
    $fetch<{ data: ApiPost }>(`${config.public.apiBase}/blog-posts/${slug.value}`)
      .then((response) => response.data)
      .catch(() => null),
  { watch: [slug] },
)

const post = computed<ApiPost | null>(
  () =>
    apiPost.value
    ?? [...DESIGN_POSTS, ...SUSTAINABILITY_POSTS].find((entry) => entry.slug === slug.value)
    ?? null,
)

// A slug matching neither the API nor the design content is a genuine 404.
if (!post.value) {
  throw createError({ statusCode: 404, statusMessage: 'Story not found', fatal: true })
}

/**
 * Genuinely related stories: same category first, then the newest of everything
 * else to fill the row. It used to be "all posts minus this one, newest first"
 * under a "More Stories" heading — which is a feed, not a relationship.
 */
const related = computed(() => {
  const others = [...DESIGN_POSTS, ...SUSTAINABILITY_POSTS]
    .filter((entry) => entry.slug !== slug.value)
    .sort((a, b) => b.published_at.localeCompare(a.published_at))

  const category = post.value?.category
  if (!category) return others.slice(0, 3)

  const sameCategory = others.filter((entry) => entry.category === category)
  const rest = others.filter((entry) => entry.category !== category)
  return [...sameCategory, ...rest].slice(0, 3)
})

useSeoMeta({
  title: () => `${post.value?.title ?? 'Story'} — Gold Coast Tokota`,
  description: () => post.value?.lede ?? post.value?.subtitle ?? undefined,
  ogTitle: () => post.value?.title,
  ogDescription: () => post.value?.lede ?? post.value?.subtitle ?? undefined,
  ogImage: () => post.value?.hero_image ?? post.value?.cover_image ?? '/brand/og-image.png',
  ogType: 'article',
  articlePublishedTime: () => post.value?.published_at,
})
</script>

<template>
  <div v-if="post" class="flex w-full flex-col items-start">
    <BlogPost :post="post" />

    <!-- The "Shop Our Products" grid that used to sit here is gone. It rendered
         the first four products in the design catalogue with no relationship to
         the story being read, and it was the single heaviest block on the page. -->
    <BlogRelatedPosts :posts="related" />
  </div>
</template>
