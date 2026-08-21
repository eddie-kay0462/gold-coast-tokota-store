<script setup lang="ts">
/**
 * Standard page heading: title, optional supporting line, and a slot for the
 * toolbar cluster the Figma frames put top-right (sort / filter / history,
 * then Import, then the primary action).
 */
defineProps<{ title: string; description?: string }>()
</script>

<template>
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <h1 class="text-title font-medium text-fg-strong">{{ title }}</h1>
      <p v-if="description" class="mt-1 max-w-prose text-ui text-fg-muted">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>
