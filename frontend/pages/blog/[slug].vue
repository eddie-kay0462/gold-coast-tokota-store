<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'

const route = useRoute()
const apiFetch = useApiFetch()

const slug = computed(() => String(route.params.slug))

// Only the API answering 404 makes this a missing story; any other failure is
// a "try again" page (see utils/apiError.ts).
const { data: post, error: postError } = await useAsyncData(
  () => `blog-${slug.value}`,
  () =>
    apiFetch<{ data: ApiPost }>(`/blog-posts/${slug.value}`)
      .then((response): ApiPost | null => response.data)
      .catch((error) => {
        if (isNotFound(error)) return null
        throw error
      }),
  { watch: [slug] },
)

if (postError.value) throw apiUnavailable()
if (!post.value) {
  throw createError({ statusCode: 404, statusMessage: 'Story not found', fatal: true })
}

// The published feed, for the related row. Left empty if it can't be fetched.
const { data: feed } = await useAsyncData('blog-related', () =>
  apiFetch<{ data: ApiPost[] }>('/blog-posts')
    .catch(() => ({ data: [] as ApiPost[] })),
)

/**
 * Genuinely related stories: same category first, then the newest of everything
 * else to fill the row. It used to be "all posts minus this one, newest first"
 * under a "More Stories" heading, which is a feed, not a relationship.
 */
const related = computed(() => {
  const others = [...(feed.value?.data ?? [])]
    .filter((entry) => entry.slug !== slug.value)
    .sort((a, b) => b.published_at.localeCompare(a.published_at))

  const category = post.value?.category
  if (!category) return others.slice(0, 3)

  const sameCategory = others.filter((entry) => entry.category === category)
  const rest = others.filter((entry) => entry.category !== category)
  return [...sameCategory, ...rest].slice(0, 3)
})

useSeoMeta({
  title: () => `${post.value?.title ?? 'Story'} | Gold Coast Tokota`,
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
