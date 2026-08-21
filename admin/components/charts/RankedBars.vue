<script setup lang="ts">
import type { SeriesPoint } from '~/types'

/**
 * Horizontal ranked bars — Figma 1:24956 "Traffic by Source" and 23:1972
 * "Onboarding Progress". The frames draw these as a segmented track rather
 * than a solid fill, which reads as a rank rather than a precise quantity.
 */
const props = withDefaults(defineProps<{ data: SeriesPoint[]; unit?: string }>(), { unit: '%' })
const max = computed(() => Math.max(1, ...props.data.map((d) => d.value)))
</script>

<template>
  <ul class="space-y-3">
    <li v-for="d in data" :key="d.label" class="flex items-center gap-3">
      <span class="w-20 shrink-0 truncate text-ui text-fg-muted sm:w-24">{{ d.label }}</span>
      <span class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-pill bg-chart-track">
        <span
          class="block h-full rounded-pill bg-chart-1"
          :style="{ width: `${(d.value / max) * 100}%` }"
        />
      </span>
      <span class="w-12 shrink-0 text-right text-meta text-fg-strong">{{ d.value }}{{ unit }}</span>
    </li>
  </ul>
</template>
