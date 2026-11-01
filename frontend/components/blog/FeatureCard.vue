<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'

defineProps<{ post: ApiPost }>()
</script>

<template>
  <!-- The large editorial card drawn in Figma (10:918), used both under an
       article ("More stories") and as the Sustainability listing tile. -->
  <NuxtLink :to="`/blog/${post.slug}`" class="group flex w-full flex-col gap-5">
    <BlogCover :post="post" aspect="aspect-[4/3]" />
    <div class="flex w-full flex-col items-start gap-3">
      <h3 class="w-full text-display-md font-light text-black group-hover:underline">
        {{ post.title }}
      </h3>
      <span
        v-if="post.category"
        class="flex items-center justify-center rounded-[30px] border border-line px-5 py-1 text-center text-caption font-normal text-black"
      >
        {{ post.category }}
      </span>
    </div>
  </NuxtLink>
</template>
