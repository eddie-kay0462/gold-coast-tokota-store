<script setup lang="ts">
const route = useRoute()
const config = useRuntimeConfig()
const { data: product } = await useAsyncData(`product-${route.params.slug}`, () =>
  $fetch(`${config.public.apiBase}/products/${route.params.slug}`),
)

useSeoMeta({
  title: () => (product.value as any)?.data?.name ?? 'Product — Gold Coast Tokota',
  description: () => (product.value as any)?.data?.description ?? undefined,
})
</script>

<template>
  <div class="mx-auto max-w-6xl px-4 py-12">
    <div v-if="product">{{ (product as any).data?.name }}</div>
    <p v-else>Loading...</p>
  </div>
</template>
