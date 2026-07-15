<script setup lang="ts">
const route = useRoute()
const config = useRuntimeConfig()
const { data: post } = await useAsyncData(`blog-${route.params.slug}`, () =>
  $fetch(`${config.public.apiBase}/blog-posts/${route.params.slug}`),
)

useSeoMeta({
  title: () => (post.value as any)?.data?.title ?? 'Stories — Gold Coast Tokota',
})
</script>

<template>
  <article class="mx-auto max-w-3xl px-4 py-12">
    <div v-if="post" v-html="(post as any).data?.body" />
    <p v-else>Loading...</p>
  </article>
</template>
