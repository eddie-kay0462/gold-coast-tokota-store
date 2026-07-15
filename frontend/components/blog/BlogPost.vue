<script setup lang="ts">
defineProps<{ post: { title: string; body: string } }>()
</script>

<template>
  <article>
    <h1 class="text-2xl font-semibold">{{ post.title }}</h1>
    <div v-html="post.body" />
  </article>
</template>
