<script setup lang="ts">
const config = useRuntimeConfig()
const { data: products } = await useAsyncData('shop-products', () =>
  $fetch(`${config.public.apiBase}/products`),
)

useSeoMeta({
  title: 'Shop — Gold Coast Tokota',
  description: 'Browse our full catalogue of handmade sandals and accessories.',
})
</script>

<template>
  <div class="mx-auto max-w-6xl px-4 py-12">
    <h1 class="text-2xl font-semibold">Shop</h1>
    <ShopProductGrid :products="(products as any)?.data ?? []" />
  </div>
</template>
