<script setup lang="ts">
const config = useRuntimeConfig()
const { data: subscribers } = await useAsyncData('admin-newsletter', () =>
  $fetch(`${config.public.apiBase}/admin/newsletter`),
)

const columns = [
  { key: 'email', label: 'Email' },
  { key: 'subscribed_at', label: 'Subscribed At' },
  { key: 'source', label: 'Source' },
]
</script>

<template>
  <div>
    <h1 class="text-xl font-semibold">Newsletter & Feedback</h1>
    <DataTable :columns="columns" :rows="(subscribers as any)?.data ?? []" class="mt-4" />
  </div>
</template>
