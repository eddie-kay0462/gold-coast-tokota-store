<script setup lang="ts">
defineProps<{ posts: { slug: string; title: string; cover_image?: string }[] }>()
</script>

<template>
  <div v-if="posts.length" class="grid gap-4 sm:grid-cols-2">
    <BlogCard v-for="post in posts" :key="post.slug" :post="post" />
  </div>
  <p v-else class="py-12 text-center text-gray-500">No stories published yet.</p>
</template>
