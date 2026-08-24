<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'

const siteSettings = useSiteSettingsStore()
const { href: whatsappHref } = useWhatsApp()
</script>

<template>
  <footer class="border-t py-10">
    <div class="mx-auto max-w-6xl space-y-4 px-4 text-sm text-gray-500">
      <div class="flex flex-wrap gap-x-6 gap-y-2">
        <a v-if="siteSettings.contactEmail" :href="`mailto:${siteSettings.contactEmail}`">{{ siteSettings.contactEmail }}</a>
        <a v-if="siteSettings.contactPhone" :href="`tel:${siteSettings.contactPhone}`">{{ siteSettings.contactPhone }}</a>
        <a v-if="siteSettings.instagramUrl" :href="siteSettings.instagramUrl" target="_blank" rel="noopener noreferrer">Instagram</a>
        <a v-if="whatsappHref" :href="whatsappHref" target="_blank" rel="noopener noreferrer">WhatsApp</a>
      </div>
      <p>&copy; {{ new Date().getFullYear() }} Gold Coast Tokota</p>
    </div>
  </footer>
</template>
