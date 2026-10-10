<script setup lang="ts">
import { PhTrendDown, PhTrendUp } from '@phosphor-icons/vue'
// Imported, not resolveComponent('NuxtLink') in the template: Nuxt registers
// it at compile time, so a runtime lookup by name fails and renders an inert
// <nuxtlink> element; the tiles looked clickable and went nowhere.
import { NuxtLink } from '#components'

/** KPI tile, Figma 1:24956. Value, optional delta, optional supporting line. */
defineProps<{
  label: string
  value: string
  delta?: number
  hint?: string
  to?: string
  tone?: 'default' | 'warning' | 'danger'
}>()
</script>

<template>
  <component
    :is="to ? NuxtLink : 'div'" :to="to"
    class="card card-pad block transition-colors"
    :class="to && 'hover:border-border-strong'"
  >
    <p class="text-ui text-fg-muted">{{ label }}</p>
    <div class="mt-2 flex flex-wrap items-baseline justify-between gap-2">
      <p
        class="text-metric-lg font-light tracking-tight"
        :class="{
          'text-fg-strong': !tone || tone === 'default',
          'text-warning': tone === 'warning',
          'text-danger': tone === 'danger',
        }"
      >{{ value }}</p>
      <span
        v-if="delta !== undefined"
        class="flex items-center gap-1 text-meta"
        :class="delta >= 0 ? 'text-success' : 'text-danger'"
      >
        {{ delta > 0 ? '+' : '' }}{{ delta.toFixed(2) }}%
        <component :is="delta >= 0 ? PhTrendUp : PhTrendDown" :size="14" />
      </span>
    </div>
    <p v-if="hint" class="mt-1 text-meta text-fg-faint">{{ hint }}</p>
  </component>
</template>
