<script setup lang="ts">
defineProps<{ height?: string; width?: string }>()
</script>

<template>
  <div
    class="animate-pulse rounded bg-gray-200"
    :style="{ height: height || '1rem', width: width || '100%' }"
  />
</template>
