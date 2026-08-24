<script setup lang="ts">
const { href } = useWhatsApp()
</script>

<template>
  <a
    v-if="href"
    :href="href"
    target="_blank"
    rel="noopener noreferrer"
    class="fixed bottom-5 right-5 z-50 rounded-full bg-green-500 p-4 text-white shadow-lg"
    aria-label="Chat on WhatsApp"
  >
    WA
  </a>
</template>
