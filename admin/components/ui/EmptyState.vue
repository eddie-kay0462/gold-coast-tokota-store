<script setup lang="ts">
import { PhTray } from '@phosphor-icons/vue'

/**
 * Explicit empty state. README Feature 1 makes the point for the storefront
 * and it holds here: an empty grid must say it is empty, not render blank.
 */
defineProps<{ title: string; description?: string; icon?: unknown }>()
</script>

<template>
  <div class="flex flex-col items-center gap-3 px-6 py-14 text-center">
    <span class="flex size-11 items-center justify-center rounded-pill bg-bg-sunken text-fg-faint">
      <component :is="icon ?? PhTray" :size="22" />
    </span>
    <p class="text-section text-fg-strong">{{ title }}</p>
    <p v-if="description" class="max-w-sm text-ui text-fg-muted">{{ description }}</p>
    <div v-if="$slots.default" class="mt-1"><slot /></div>
  </div>
</template>
