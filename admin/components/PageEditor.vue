<script setup lang="ts">
// Rich-text editor shell reused for both the About page and Blog posts.
// Server-side HTML sanitization on save is mandatory (see Feature 9 edge
// cases) — this component only produces the raw HTML, never trusts it.
const model = defineModel<string>({ required: true })
</script>

<template>
  <div class="rounded border">
    <div class="border-b bg-gray-50 px-3 py-2 text-xs text-gray-500">Rich text editor</div>
    <textarea v-model="model" class="min-h-[300px] w-full p-3" />
  </div>
</template>
