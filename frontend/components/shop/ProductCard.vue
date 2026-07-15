<script setup lang="ts">
defineProps<{
  product: { slug: string; name: string; base_price_ghs: number; images?: string[] }
}>()
</script>

<template>
  <NuxtLink :to="`/shop/${product.slug}`" class="block rounded-lg border p-4">
    <img
      :src="product.images?.[0] || '/placeholder-product.png'"
      :alt="product.name"
      class="aspect-square w-full rounded object-cover"
    >
    <h3 class="mt-2 font-medium">{{ product.name }}</h3>
    <CommonPriceDisplay :base-price-ghs="product.base_price_ghs" />
  </NuxtLink>
</template>
