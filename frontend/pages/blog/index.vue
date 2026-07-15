<script setup lang="ts">
const config = useRuntimeConfig()
const { data: posts } = await useAsyncData('blog-posts', () =>
  $fetch(`${config.public.apiBase}/blog-posts`),
)

useSeoMeta({
  title: 'Stories — Gold Coast Tokota',
  description: 'Brand stories and updates from Gold Coast Tokota.',
})
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 py-12">
    <h1 class="text-2xl font-semibold">Stories</h1>
    <BlogList :posts="(posts as any)?.data ?? []" />
  </div>
</template>
