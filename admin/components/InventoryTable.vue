<script setup lang="ts">
defineProps<{ items: Record<string, unknown>[] }>()

const columns = [
  { key: 'product', label: 'Product' },
  { key: 'quantity_available', label: 'Available' },
  { key: 'quantity_reserved', label: 'Reserved' },
  { key: 'low_stock_threshold', label: 'Low Stock At' },
]
</script>

<template>
  <DataTable :columns="columns" :rows="items" />
</template>
