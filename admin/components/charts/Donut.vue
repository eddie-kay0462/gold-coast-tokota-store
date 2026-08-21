<script setup lang="ts">
import type { SeriesPoint } from '~/types'

/** Donut with a legend — Figma 1:24956, "Traffic by Location". */
const props = withDefaults(defineProps<{ data: SeriesPoint[]; size?: number; unit?: string }>(), {
  size: 150, unit: '%',
})

const total = computed(() => props.data.reduce((s, d) => s + d.value, 0) || 1)

/** Stroke-dasharray around a circle beats arc maths and stays crisp at any size. */
const R = 42
const C = 2 * Math.PI * R
/**
 * A hairline gap between slices. Colour alone should not have to carry the
 * boundary — with a gap, two neighbouring segments stay readable even for a
 * viewer who cannot distinguish their hues.
 */
const GAP = 1.5

const segments = computed(() => {
  let offset = 0
  return props.data.map((d, i) => {
    const frac = d.value / total.value
    const len = Math.max(0, frac * C - GAP)
    const seg = { ...d, index: i + 1, dash: len, offset: -offset * C }
    offset += frac
    return seg
  })
})
</script>

<template>
  <div class="flex flex-wrap items-center gap-5">
    <svg
      :width="size" :height="size" viewBox="0 0 100 100" class="shrink-0"
      role="img" aria-label="Distribution chart"
    >
      <g transform="rotate(-90 50 50)">
        <circle cx="50" cy="50" :r="R" fill="none" stroke="rgb(var(--chart-track))" stroke-width="14" />
        <circle
          v-for="s in segments" :key="s.label"
          cx="50" cy="50" :r="R" fill="none" stroke-width="14"
          :stroke="`rgb(var(--chart-${((s.index - 1) % 6) + 1}))`"
          stroke-linecap="butt"
          :stroke-dasharray="`${s.dash} ${C - s.dash}`"
          :stroke-dashoffset="s.offset"
        />
      </g>
    </svg>

    <ul class="min-w-0 flex-1 space-y-2">
      <li v-for="s in segments" :key="s.label" class="flex items-center gap-2">
        <span
          class="size-2 shrink-0 rounded-pill"
          :style="{ backgroundColor: `rgb(var(--chart-${((s.index - 1) % 6) + 1}))` }"
        />
        <span class="min-w-0 flex-1 truncate text-ui text-fg-muted">{{ s.label }}</span>
        <span class="shrink-0 text-ui text-fg-strong">{{ s.value }}{{ unit }}</span>
      </li>
    </ul>
  </div>
</template>
