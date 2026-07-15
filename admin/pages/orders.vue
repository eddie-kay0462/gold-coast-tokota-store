<script setup lang="ts">
const config = useRuntimeConfig()
const { data: orders } = await useAsyncData('admin-orders', () =>
  $fetch(`${config.public.apiBase}/admin/orders`),
)
</script>

<template>
  <div>
    <h1 class="text-xl font-semibold">Orders</h1>
    <OrdersTable :orders="(orders as any)?.data ?? []" class="mt-4" />
  </div>
</template>
