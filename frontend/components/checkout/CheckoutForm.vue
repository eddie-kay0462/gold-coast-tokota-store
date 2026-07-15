<script setup lang="ts">
const shippingAddress = reactive({
  fullName: '',
  line1: '',
  city: '',
  country: 'GH',
  phone: '',
})
</script>

<template>
  <form class="mt-6 space-y-4">
    <FormsFormField v-model="shippingAddress.fullName" label="Full name" name="fullName" required />
    <FormsFormField v-model="shippingAddress.line1" label="Address" name="line1" required />
    <FormsFormField v-model="shippingAddress.city" label="City" name="city" required />
    <FormsFormField v-model="shippingAddress.phone" label="Phone" name="phone" required />
    <button type="submit" class="w-full rounded bg-black py-3 text-white">Continue to payment</button>
  </form>
</template>
