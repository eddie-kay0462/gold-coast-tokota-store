<script setup lang="ts">
const config = useRuntimeConfig()
const { data: page } = await useAsyncData('about-page', () =>
  $fetch(`${config.public.apiBase}/pages/about`),
)

useSeoMeta({
  title: 'About — Gold Coast Tokota',
  description: 'The story behind Gold Coast Tokota.',
})
</script>

<template>
  <div class="mx-auto max-w-3xl px-4 py-16">
    <div v-if="page" v-html="(page as any).data?.body" />
    <p v-else>Loading...</p>
  </div>
</template>
