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
      <FormsFormField
        v-model="form.measurements"
        label="Sandal specs / measurements"
        name="measurements"
        type="textarea"
        required
      />
      <div class="flex w-full min-w-0 flex-col gap-1.5">
        <label for="reference_image" class="text-caption font-normal text-graphite">Reference image</label>
        <!-- A bare file input has an unbounded intrinsic width; `w-full min-w-0`
             keeps it inside a 320px viewport. -->
        <input
          id="reference_image"
          type="file"
          accept="image/*"
          class="w-full min-w-0 border border-line bg-white p-2.5 text-caption text-graphite file:mr-3 file:min-h-[36px] file:border-0 file:bg-surface file:px-3 file:text-caption file:text-graphite"
          @change="onFileChange"
        >
      </div>
      <FormsFormField v-model="form.name" label="Name" name="name" required />
      <FormsFormField v-model="form.email" label="Email" name="email" type="email" required />
      <FormsFormField v-model="form.phone" label="Phone" name="phone" required />
      <CommonBrandButton full type="submit">Submit DIY Order</CommonBrandButton>
    </form>
    <p v-else>Order submitted — check your email/SMS for confirmation.</p>
  </div>
</template>
