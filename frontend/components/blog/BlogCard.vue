<script setup lang="ts">
defineProps<{ post: { slug: string; title: string; cover_image?: string } }>()
</script>

<template>
  <NuxtLink :to="`/blog/${post.slug}`" class="block rounded-lg border p-4">
    <img
      v-if="post.cover_image"
      :src="post.cover_image"
      :alt="post.title"
      class="aspect-video w-full rounded object-cover"
    >
    <h3 class="mt-2 font-medium">{{ post.title }}</h3>
  </NuxtLink>
</template>
