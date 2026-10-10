<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'

defineProps<{ posts: ApiPost[] }>()
</script>

<template>
  <!-- The rail's tight 6px gutter between photos, so the grid and the home
       page's Stories row read as the same tiles. -->
  <ul v-if="posts.length" class="grid w-full grid-cols-1 gap-x-1.5 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
    <li v-for="post in posts" :key="post.slug" class="min-w-0">
      <BlogCard :post="post" />
    </li>
  </ul>

  <p v-else class="w-full py-12 text-center text-body text-muted">
    No stories yet. Check back soon.
  </p>
</template>
