<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'

defineProps<{ posts: ApiPost[] }>()
</script>

<template>
  <!-- Owns its own section chrome (gutter, rhythm, heading) so it can be dropped
       straight into a page without a wrapper re-stating the padding — the wrapper
       is what previously zeroed the desktop gutter and left the grid flush to the
       viewport edge. Heading matches "Shop Our Products" so the two read as siblings. -->
  <section v-if="posts.length" class="page-gutter section-y flex w-full flex-col items-center gap-10">
    <h2 class="w-full text-center text-display-md font-normal text-black lg:text-article-lg">
      More Stories
    </h2>
    <ul class="grid w-full grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="post in posts" :key="post.slug" class="min-w-0">
        <BlogFeatureCard :post="post" />
      </li>
    </ul>
  </section>
</template>
