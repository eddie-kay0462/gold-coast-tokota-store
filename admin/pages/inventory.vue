<script setup lang="ts">
const config = useRuntimeConfig()
const { data: inventory } = await useAsyncData('admin-inventory', () =>
  $fetch(`${config.public.apiBase}/admin/inventory`),
)
</script>

<template>
  <div>
    <h1 class="text-xl font-semibold">Inventory</h1>
    <InventoryTable :items="(inventory as any)?.data ?? []" class="mt-4" />
  </div>
</template>
