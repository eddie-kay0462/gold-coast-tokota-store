<script setup lang="ts">
defineProps<{ currency: 'GHS' | 'USD' }>()
</script>

<template>
  <div>
    <p v-if="currency === 'GHS'">Paying via Paystack</p>
    <p v-else>Paying via Stripe</p>
  </div>
</template>
