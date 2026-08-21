<script setup lang="ts">
import type { ApiProduct } from '~/utils/catalog'

defineProps<{
  products: ApiProduct[]
  /** Renders placeholder cards instead of the grid while the catalogue loads. */
  pending?: boolean
}>()
</script>

<template>
  <!-- Skeletons keep the grid's footprint stable so nothing shifts on arrival.
       The placeholder must therefore share the card's aspect ratio rather than
       carry a flat pixel height, which only matched the card at one width. -->
  <div v-if="pending" class="grid grid-cols-2 gap-x-5 gap-y-6 lg:grid-cols-3 2xl:grid-cols-4">
    <div v-for="n in 6" :key="n" class="flex flex-col gap-2.5">
      <CommonSkeletonLoader class="aspect-[9/10] w-full" />
      <CommonSkeletonLoader height="12px" width="70%" />
      <CommonSkeletonLoader height="12px" width="40%" />
    </div>
  </div>

  <ul v-else-if="products.length" class="grid grid-cols-2 gap-x-5 gap-y-6 lg:grid-cols-3 2xl:grid-cols-4">
    <li v-for="product in products" :key="product.slug" class="min-w-0">
      <ShopProductCard :product="product" />
    </li>
  </ul>

  <div v-else class="flex flex-col items-start gap-2 py-16">
    <p class="text-body text-graphite">No products match these filters.</p>
    <p class="text-caption text-muted">
      Try clearing a filter or two — or browse the full collection.
    </p>
  </div>
</template>
