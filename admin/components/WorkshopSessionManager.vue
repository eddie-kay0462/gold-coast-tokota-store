<script setup lang="ts">
const form = reactive({ scheduledDate: '', scheduledSlot: '', capacity: 8, locationNotes: '' })

async function onSubmit() {
  const config = useRuntimeConfig()
  await $fetch(`${config.public.apiBase}/admin/workshop-sessions`, {
    method: 'POST',
    body: {
      scheduled_date: form.scheduledDate,
      scheduled_slot: form.scheduledSlot,
      capacity: form.capacity,
      location_notes: form.locationNotes,
    },
  })
}
</script>

<template>
  <form class="space-y-4" @submit.prevent="onSubmit">
    <FormField v-model="form.scheduledDate" label="Date" name="scheduledDate" type="date" required />
    <FormField v-model="form.scheduledSlot" label="Time Slot" name="scheduledSlot" required />
    <FormField v-model.number="form.capacity" label="Capacity" name="capacity" type="number" required />
    <FormField v-model="form.locationNotes" label="Location Notes" name="locationNotes" />
    <button type="submit" class="rounded bg-black px-4 py-2 text-white">Create Session</button>
  </form>
</template>
