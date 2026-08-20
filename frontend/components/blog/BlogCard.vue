<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'
import { formatPostDate } from '~/utils/newsPosts'

defineProps<{ post: ApiPost }>()
</script>

<template>
  <NuxtLink :to="`/blog/${post.slug}`" class="group flex w-full flex-col gap-2">
    <img
      :src="post.cover_image || '/design/news-placeholder.png'"
      :alt="post.title"
      class="h-[220px] w-full object-cover lg:h-[280px]"
      loading="lazy"
    >
    <div class="flex w-full flex-col gap-1 font-light text-black">
      <p class="w-full text-tag">{{ formatPostDate(post.published_at) }}</p>
      <h3 class="w-full text-body group-hover:underline">{{ post.title }}</h3>
    </div>
  </NuxtLink>
</template>
