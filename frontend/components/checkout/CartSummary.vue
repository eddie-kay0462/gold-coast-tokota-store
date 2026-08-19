<script setup lang="ts">
import { useCartStore } from '~/stores/cart'

const cart = useCartStore()

const savingsGhs = computed(() => cart.compareAtSubtotalGhs - cart.subtotalGhs)
</script>

<template>
  <div class="border border-line p-4">
    <ul v-if="!cart.isEmpty" class="flex flex-col gap-4">
      <li v-for="item in cart.items" :key="item.inventoryItemId" class="flex items-start gap-4">
        <img
          v-if="item.image"
          :src="item.image"
          :alt="item.name"
          class="h-[80px] w-[56px] shrink-0 object-cover"
          loading="lazy"
        >
        <div class="flex min-w-0 flex-1 flex-col">
          <span class="text-label text-black">{{ item.name }}</span>
          <span v-if="item.variantLabel" class="text-caption text-muted">
            {{ item.variantLabel }} · Qty {{ item.quantity }}
          </span>
        </div>
        <CommonPriceDisplay
          class="shrink-0 whitespace-nowrap text-caption text-graphite"
          :base-price-ghs="item.unitPriceGhs * item.quantity"
          :compare-at-ghs="item.compareAtGhs ? item.compareAtGhs * item.quantity : null"
          compact
        />
      </li>
    </ul>

    <p v-else class="py-4 text-caption text-muted">Your cart is empty.</p>

    <div v-if="!cart.isEmpty" class="mt-4 flex flex-col gap-1 border-t border-line pt-4">
      <div class="flex items-center justify-between text-body font-normal text-black">
        <span>Subtotal ({{ cart.itemCount }} {{ cart.itemCount === 1 ? 'item' : 'items' }})</span>
        <CommonPriceDisplay :base-price-ghs="cart.subtotalGhs" compact />
      </div>
      <p v-if="savingsGhs > 0" class="flex items-center justify-between text-caption text-sale">
        <span>You save</span>
        <CommonPriceDisplay :base-price-ghs="savingsGhs" compact />
      </p>
    </div>
  </div>
</template>
