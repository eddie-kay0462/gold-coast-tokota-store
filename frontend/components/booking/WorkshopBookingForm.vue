<script setup lang="ts">
import { useBookingStore } from '~/stores/bookings'

const bookingStore = useBookingStore()
const form = reactive({ attendeeCount: 1, name: '', email: '', phone: '' })
const submitted = ref(false)

async function onSubmit() {
  const config = useRuntimeConfig()
  await $fetch(`${config.public.apiBase}/bookings`, {
    method: 'POST',
    body: {
      type: 'workshop',
      workshop_session_id: bookingStore.selectedWorkshopSessionId,
      details: { attendee_count: form.attendeeCount },
      name: form.name,
      email: form.email,
      phone: form.phone,
    },
  })
  submitted.value = true
}
</script>

<template>
  <div class="mt-6">
    <BookingCalendar mode="workshop" :sessions="[]" @select-session="bookingStore.selectWorkshopSession($event, 0)" />
    <form v-if="!submitted" class="mt-4 space-y-4" @submit.prevent="onSubmit">
      <FormsFormField v-model="form.name" label="Name" name="name" required />
      <FormsFormField v-model="form.email" label="Email" name="email" type="email" required />
      <FormsFormField v-model="form.phone" label="Phone" name="phone" required />
      <CommonBrandButton full type="submit">Book Workshop</CommonBrandButton>
    </form>
    <p v-else>Booking submitted — check your email/SMS for confirmation.</p>
  </div>
</template>
