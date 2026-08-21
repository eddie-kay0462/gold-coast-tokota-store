<script setup lang="ts">
/**
 * A pulsing placeholder. Pass `height`/`width` for a fixed box, or leave both
 * off and size it with utility classes on the element itself (`aspect-[9/10]`,
 * `w-full`) — which is what callers standing in for a responsive image should
 * do, so the placeholder and the real thing occupy the same space at every
 * width. `max-w-full` keeps an oversized `width` prop from overflowing.
 */
defineProps<{ height?: string; width?: string }>()
</script>

<template>
  <div
    class="max-w-full animate-pulse rounded bg-gray-200"
    :style="{ ...(height ? { height } : {}), ...(width ? { width } : {}) }"
  />
</template>
