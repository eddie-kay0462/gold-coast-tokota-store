<script setup lang="ts">
const config = useRuntimeConfig()
const { data: settings } = await useAsyncData('site-settings', () =>
  $fetch<{ data: Record<string, string> }>(`${config.public.apiBase}/site-settings`),
)

const form = reactive({
  whatsappNumber: settings.value?.data.whatsapp_number ?? '',
  whatsappDefaultMessage: settings.value?.data.whatsapp_default_message ?? '',
  contactEmail: settings.value?.data.contact_email ?? '',
  contactPhone: settings.value?.data.contact_phone ?? '',
  instagramUrl: settings.value?.data.instagram_url ?? '',
  heroHeadline: settings.value?.data.hero_headline ?? '',
  diyTurnaroundEstimate: settings.value?.data.diy_turnaround_estimate ?? '',
})

async function onSubmit() {
  await $fetch(`${config.public.apiBase}/admin/site-settings`, { method: 'PUT', body: form })
}
</script>

<template>
  <form class="space-y-4" @submit.prevent="onSubmit">
    <FormField v-model="form.whatsappNumber" label="WhatsApp Number" name="whatsappNumber" />
    <FormField v-model="form.whatsappDefaultMessage" label="WhatsApp Default Message" name="whatsappDefaultMessage" />
    <FormField v-model="form.contactEmail" label="Contact Email" name="contactEmail" type="email" />
    <FormField v-model="form.contactPhone" label="Contact Phone" name="contactPhone" />
    <FormField v-model="form.instagramUrl" label="Instagram URL" name="instagramUrl" />
    <FormField v-model="form.heroHeadline" label="Hero Headline" name="heroHeadline" />
    <FormField v-model="form.diyTurnaroundEstimate" label="DIY Turnaround Estimate" name="diyTurnaroundEstimate" />
    <button type="submit" class="rounded bg-black px-4 py-2 text-white">Save Settings</button>
  </form>
</template>
