<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'

const siteSettings = useSiteSettingsStore()
const form = reactive({ measurements: '', name: '', email: '', phone: '', pickupOrDelivery: 'pickup' })
const referenceImage = ref<File | null>(null)
const submitted = ref(false)

function onFileChange(event: Event) {
  referenceImage.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

async function onSubmit() {
  const config = useRuntimeConfig()
  const body = new FormData()
  body.append('type', 'diy_order')
  body.append('details[measurements]', form.measurements)
  body.append('details[pickup_or_delivery]', form.pickupOrDelivery)
  body.append('name', form.name)
  body.append('email', form.email)
  body.append('phone', form.phone)
  if (referenceImage.value) body.append('reference_image', referenceImage.value)

  await $fetch(`${config.public.apiBase}/bookings`, { method: 'POST', body })
  submitted.value = true
}
</script>

<template>
  <div class="mt-6">
    <BookingCalendar mode="diy" :diy-turnaround-estimate="siteSettings.diyTurnaroundEstimate" />
    <form v-if="!submitted" class="mt-4 space-y-4" @submit.prevent="onSubmit">
      <FormsFormField v-model="form.measurements" label="Sandal specs / measurements" name="measurements" required />
      <div>
        <label for="reference_image" class="mb-1 block text-sm font-medium">Reference image</label>
        <input id="reference_image" type="file" accept="image/*" @change="onFileChange">
      </div>
      <FormsFormField v-model="form.name" label="Name" name="name" required />
      <FormsFormField v-model="form.email" label="Email" name="email" type="email" required />
      <FormsFormField v-model="form.phone" label="Phone" name="phone" required />
      <button type="submit" class="rounded bg-black px-4 py-2 text-white">Submit DIY Order</button>
    </form>
    <p v-else>Order submitted — check your email/SMS for confirmation.</p>
  </div>
</template>
