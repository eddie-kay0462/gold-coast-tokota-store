<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'
import { formatPostDate } from '~/utils/newsPosts'

defineProps<{ post: ApiPost }>()
</script>

<template>
  <!-- The home page's Stories tile: tall photo, tracked capitals, the deck in
       grey and the date beneath it. -->
  <NuxtLink :to="`/blog/${post.slug}`" class="group flex w-full flex-col gap-4">
    <BlogCover :post="post" aspect="aspect-[4/5]" />
    <div class="flex w-full flex-col items-start gap-1 pr-2">
      <h3 class="caps-title w-full group-hover:underline">{{ post.title }}</h3>
      <span v-if="post.subtitle" class="text-label tracking-[0.4px] text-subtle">{{ post.subtitle }}</span>
      <span class="text-caption text-muted">{{ formatPostDate(post.published_at) }}</span>
    </div>
  </NuxtLink>
</template>
