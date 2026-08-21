<script setup lang="ts">
import type { SeriesPoint } from '~/types'

/** Vertical bars — Figma 1:24956, "Traffic by Device". */
const props = withDefaults(defineProps<{ data: SeriesPoint[]; height?: number }>(), { height: 200 })

const max = computed(() => Math.max(1, ...props.data.map((d) => d.value)))
// The kit draws every bar the same flat grey. Tinting the leader gold gives
// the panel the focal point it otherwise lacks, without adding a legend.
const leader = computed(() => props.data.findIndex((d) => d.value === max.value))
const fmt = (n: number) => new Intl.NumberFormat('en-GB', { notation: 'compact' }).format(n)
</script>

<template>
  <div>
    <div class="flex items-end gap-2" :style="{ height: `${height}px` }">
      <div v-for="(d, i) in data" :key="d.label" class="flex min-w-0 flex-1 flex-col justify-end">
        <span
          class="mb-1 truncate text-center text-micro"
          :class="i === leader ? 'font-medium text-fg-strong' : 'text-fg-faint'"
        >{{ fmt(d.value) }}</span>
        <div
          class="rounded-t-sm transition-[height]"
          :class="i === leader ? 'bg-chart-1' : 'bg-chart-track'"
          :style="{ height: `${Math.max(2, (d.value / max) * (height - 24))}px` }"
        />
      </div>
    </div>
    <div class="mt-2 flex gap-2">
      <span
        v-for="(d, i) in data" :key="d.label"
        class="min-w-0 flex-1 truncate text-center text-meta"
        :class="i === leader ? 'text-fg-strong' : 'text-fg-muted'"
      >
        {{ d.label }}
      </span>
    </div>
  </div>
</template>
