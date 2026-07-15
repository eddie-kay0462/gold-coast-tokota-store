<script setup lang="ts">
const props = defineProps<{
  status: 'pending' | 'paid' | 'processing' | 'shipped' | 'delivered' | 'cancelled' | 'refunded' | 'inventory_conflict' | 'confirmed' | 'waitlisted' | 'completed'
}>()

const colorMap: Record<string, string> = {
  pending: 'bg-gray-100 text-gray-700',
  paid: 'bg-green-100 text-green-700',
  processing: 'bg-blue-100 text-blue-700',
  shipped: 'bg-blue-100 text-blue-700',
  delivered: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
  refunded: 'bg-red-100 text-red-700',
  inventory_conflict: 'bg-amber-100 text-amber-700',
  confirmed: 'bg-green-100 text-green-700',
  waitlisted: 'bg-amber-100 text-amber-700',
  completed: 'bg-green-100 text-green-700',
}
</script>

<template>
  <span class="rounded-full px-2 py-1 text-xs font-medium" :class="colorMap[props.status]">
    {{ props.status }}
  </span>
</template>
