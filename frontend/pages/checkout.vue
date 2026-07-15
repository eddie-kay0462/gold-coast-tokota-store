<script setup lang="ts">
// SPA-only route (see nuxt.config.ts routeRules) — no SEO requirement.
definePageMeta({ layout: 'default' })
</script>

<template>
  <div class="mx-auto max-w-3xl px-4 py-12">
    <h1 class="text-2xl font-semibold">Checkout</h1>
    <CheckoutCartSummary />
    <CheckoutForm />
  </div>
</template>
