<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'

defineProps<{ posts: ApiPost[] }>()
</script>

<template>
  <ul v-if="posts.length" class="grid w-full grid-cols-1 gap-x-[30px] gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
    <li v-for="post in posts" :key="post.slug" class="min-w-0">
      <BlogCard :post="post" />
    </li>
  </ul>

  <p v-else class="w-full py-12 text-center text-body text-muted">
    No stories published yet — check back soon.
  </p>
</template>
