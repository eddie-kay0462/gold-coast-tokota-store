<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'

defineProps<{ posts: ApiPost[] }>()
</script>

<template>
  <section v-if="posts.length" class="w-full">
    <h2 class="sr-only">More stories</h2>
    <ul class="grid w-full grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="post in posts" :key="post.slug" class="min-w-0">
        <BlogFeatureCard :post="post" />
      </li>
    </ul>
  </section>
</template>
