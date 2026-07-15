<script setup lang="ts">
import { useCartStore } from '~/stores/cart'

const cart = useCartStore()
</script>

<template>
  <div class="rounded-lg border p-4">
    <ul>
      <li v-for="item in cart.items" :key="item.inventoryItemId" class="flex justify-between py-1">
        <span>{{ item.name }} × {{ item.quantity }}</span>
        <CommonPriceDisplay :base-price-ghs="item.unitPriceGhs * item.quantity" />
      </li>
    </ul>
    <div class="mt-2 flex justify-between border-t pt-2 font-semibold">
      <span>Subtotal</span>
      <CommonPriceDisplay :base-price-ghs="cart.subtotalGhs" />
    </div>
  </div>
</template>
