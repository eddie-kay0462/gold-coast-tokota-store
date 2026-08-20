<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'
import { DESIGN_POSTS, RELATED_POSTS } from '~/utils/newsPosts'
import { DESIGN_PRODUCTS } from '~/utils/designCatalogue'

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
    ?? [...DESIGN_POSTS, ...RELATED_POSTS].find((entry) => entry.slug === slug.value)
    ?? null,
)

// A slug matching neither the API nor the design content is a genuine 404.
if (!post.value) {
  throw createError({ statusCode: 404, statusMessage: 'Story not found', fatal: true })
}

/** Other stories, newest first, excluding the one being read. */
const related = computed(() =>
  [...DESIGN_POSTS, ...RELATED_POSTS]
    .filter((entry) => entry.slug !== slug.value)
    .sort((a, b) => b.published_at.localeCompare(a.published_at))
    .slice(0, 3),
)

const products = computed(() => DESIGN_PRODUCTS.slice(0, 4))

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

    <!-- Shop Our Products -->
    <section class="flex w-full flex-col items-center gap-10 px-5 py-16 lg:p-[60px]">
      <h2 class="w-full text-center text-display-md font-normal text-black lg:text-article-lg">
        Shop Our Products
      </h2>

      <ul class="grid w-full grid-cols-2 gap-x-6 gap-y-8 lg:grid-cols-4">
        <li v-for="product in products" :key="product.slug" class="min-w-0">
          <ShopProductCard :product="product" />
        </li>
      </ul>

      <CommonBrandButton to="/shop">Shop Now</CommonBrandButton>
    </section>

    <div class="w-full px-5 pb-16 lg:px-0 lg:pb-0">
      <BlogRelatedPosts :posts="related" />
    </div>
  </div>
</template>
