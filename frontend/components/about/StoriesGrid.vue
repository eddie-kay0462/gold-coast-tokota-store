<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'

defineProps<{ posts: ApiPost[] }>()
</script>

<template>
  <!--
    Figma 10:976, condensed. On the old `/sustainability` page this was two rows
    of six with a "Load More Articles" button that paged through the whole
    programme feed.

    On the merged page that made it a second, filtered copy of `/blog` sitting
    two thirds of the way down About — and `/blog` gained its own category
    filter on 27 Aug, so the full list has a proper home. One row of three and a
    link into that filtered view says the same thing without the duplication.
  -->
  <section
    id="stories"
    class="page-gutter section-y mx-auto flex w-full max-w-[1440px] scroll-mt-8 flex-col items-start gap-3"
  >
    <div class="flex w-full flex-wrap items-baseline justify-between gap-4">
      <h2 class="font-normal text-display-heading text-black">The Latest</h2>
      <NuxtLink
        to="/blog?category=Sustainability"
        class="-my-3 flex min-h-[44px] items-center py-3 text-label text-graphite underline"
      >
        All sustainability stories
      </NuxtLink>
    </div>

    <ul class="grid w-full grid-cols-1 gap-x-6 gap-y-12 sm:grid-cols-2 md:grid-cols-3">
      <li v-for="post in posts" :key="post.slug" class="min-w-0">
        <BlogFeatureCard :post="post" />
      </li>
    </ul>
  </section>
</template>
