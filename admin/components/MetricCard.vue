<script setup lang="ts">
defineProps<{ label: string; value: string | number; hint?: string }>()
</script>

<template>
  <div class="rounded-lg border p-4">
    <p class="text-sm text-gray-500">{{ label }}</p>
    <p class="mt-1 text-2xl font-semibold">{{ value }}</p>
    <p v-if="hint" class="mt-1 text-xs text-gray-400">{{ hint }}</p>
  </div>
</template>
