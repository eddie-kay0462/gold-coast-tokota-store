<script setup lang="ts">
const auth = useAuthStore()
const config = useRuntimeConfig()
const { data: products } = await useAsyncData('admin-products', () =>
  $fetch(`${config.public.apiBase}/admin/products`),
)

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'base_price_ghs', label: 'Price (GHS)' },
  { key: 'is_active', label: 'Active' },
]
</script>

<template>
  <div>
    <h1 class="text-xl font-semibold">Products</h1>
    <p v-if="auth.role !== 'admin'" class="mt-4 text-red-600">
      You don't have permission to view this page.
    </p>
    <DataTable v-else :columns="columns" :rows="(products as any)?.data ?? []" class="mt-4" />
  </div>
</template>
