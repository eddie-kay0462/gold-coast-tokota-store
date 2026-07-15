<script setup lang="ts">
defineProps<{ orders: Record<string, unknown>[] }>()

const columns = [
  { key: 'id', label: 'Order' },
  { key: 'customer', label: 'Customer' },
  { key: 'total', label: 'Total' },
  { key: 'status', label: 'Status' },
]
</script>

<template>
  <DataTable :columns="columns" :rows="orders">
    <template #status="{ row }">
      <StatusBadge :status="row.status as any" />
    </template>
  </DataTable>
</template>
