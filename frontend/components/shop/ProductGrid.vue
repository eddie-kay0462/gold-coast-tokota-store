<script setup lang="ts">
defineProps<{
  products: { slug: string; name: string; base_price_ghs: number; images?: string[] }[]
}>()
</script>

<template>
  <div v-if="products.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
    <ShopProductCard v-for="product in products" :key="product.slug" :product="product" />
  </div>
  <p v-else class="py-12 text-center text-gray-500">No products available yet — check back soon.</p>
</template>
