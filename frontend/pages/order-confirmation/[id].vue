<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route = useRoute()
const config = useRuntimeConfig()
const { data: order } = await useAsyncData(`order-${route.params.id}`, () =>
  $fetch(`${config.public.apiBase}/orders/${route.params.id}`),
)
</script>

<template>
  <div class="page-gutter section-y mx-auto w-full max-w-[calc(48rem+120px)]">
    <h1 class="text-2xl font-semibold">Order Confirmation</h1>
    <div v-if="order">Order #{{ (order as any).data?.id }} — {{ (order as any).data?.status }}</div>
  </div>
</template>
