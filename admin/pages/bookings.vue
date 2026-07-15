<script setup lang="ts">
const config = useRuntimeConfig()
const { data: bookings } = await useAsyncData('admin-bookings', () =>
  $fetch(`${config.public.apiBase}/admin/bookings`),
)

const columns = [
  { key: 'type', label: 'Type' },
  { key: 'customer', label: 'Customer' },
  { key: 'scheduled_date', label: 'Date' },
  { key: 'status', label: 'Status' },
]
</script>

<template>
  <div>
    <h1 class="text-xl font-semibold">Bookings & Workshop Sessions</h1>
    <DataTable :columns="columns" :rows="(bookings as any)?.data ?? []" class="mt-4">
      <template #status="{ row }">
        <StatusBadge :status="row.status" />
      </template>
    </DataTable>
    <h2 class="mt-8 text-lg font-semibold">Create Workshop Session</h2>
    <WorkshopSessionManager class="mt-4" />
  </div>
</template>
