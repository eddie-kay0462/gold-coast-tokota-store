<script setup lang="ts">
import { useCartStore } from '~/stores/cart'

// SPA-only route (see nuxt.config.ts routeRules) — no SEO requirement.
definePageMeta({ layout: 'default' })

const cart = useCartStore()
</script>

<template>
  <div class="mx-auto max-w-3xl px-4 py-12">
    <h1 class="text-display-sm font-normal text-black">Checkout</h1>

    <!-- Nothing to pay for — send people back to the shop rather than showing
         an address form against an empty cart. -->
    <div v-if="cart.isEmpty" class="mt-6 flex flex-col items-start gap-4">
      <p class="text-body text-graphite">Your cart is empty.</p>
      <CommonBrandButton to="/shop">Shop Sandals</CommonBrandButton>
    </div>

    <template v-else>
      <div class="mt-6">
        <CheckoutCartSummary />
      </div>
      <CheckoutForm />
    </template>
  </div>
</template>
