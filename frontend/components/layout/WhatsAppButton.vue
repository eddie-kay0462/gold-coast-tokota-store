<script setup lang="ts">
import { PhWhatsappLogo as WhatsappLogo } from '@phosphor-icons/vue'

const { href } = useWhatsApp()
</script>

<template>
  <!-- Sits at z-40, below the header (z-50) and every overlay, so it can never
       cover a dialog. `env(safe-area-inset-bottom)` keeps it clear of the iOS
       home indicator; `layouts/default.vue` reserves the space it occupies so
       it does not sit on the footer's legal links. -->
  <a
    v-if="href"
    :href="href"
    target="_blank"
    rel="noopener noreferrer"
    class="fixed bottom-[max(1.25rem,env(safe-area-inset-bottom))] right-5 z-40 flex size-14 items-center justify-center rounded-full bg-green-500 text-white shadow-lg"
    aria-label="Chat on WhatsApp"
  >
    <WhatsappLogo :size="28" weight="fill" aria-hidden="true" />
  </a>
</template>
