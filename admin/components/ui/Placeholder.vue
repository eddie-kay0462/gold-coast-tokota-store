<script setup lang="ts">
import { PhHammer } from '@phosphor-icons/vue'

/**
 * Marks a route that exists and is reachable but whose content is still to be
 * built. Explicit beats a blank page: someone reviewing the nav should be able
 * to tell "not built yet" from "broken".
 */
defineProps<{ title: string; note?: string }>()
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader :title="title" />
    <div class="card flex flex-col items-center gap-3 px-6 py-16 text-center">
      <span class="flex size-11 items-center justify-center rounded-pill bg-bg-sunken text-fg-faint">
        <PhHammer :size="22" />
      </span>
      <p class="text-section text-fg-strong">Not built yet</p>
      <p class="max-w-sm text-ui text-fg-muted">
        {{ note ?? 'This screen is scaffolded so the navigation is complete, but its content is still to come.' }}
      </p>
    </div>
  </div>
</template>
