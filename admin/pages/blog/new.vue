<script setup lang="ts">
/**
 * New post reuses the `[id]` editor with an empty document — the two screens
 * are the same screen. Nuxt matches a static segment before a dynamic one, so
 * this file needs to exist even though it renders the same component.
 */
</script>

<template>
  <BlogPostEditor />
</template>
