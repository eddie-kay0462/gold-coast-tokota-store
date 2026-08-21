<script setup lang="ts">
import { PhCalendarDots } from '@phosphor-icons/vue'

/**
 * Range selector for reporting screens.
 *
 * Presets plus two native date inputs rather than a custom calendar: the
 * native control is keyboard-accessible, localised and mobile-friendly for
 * free, and a hand-rolled two-month calendar is a lot of surface to get wrong
 * for a screen where "last 30 days" answers most questions.
 */
export interface DateRange { from: string; to: string; label: string }

const model = defineModel<DateRange>({ required: true })
const props = defineProps<{ today?: string }>()

const anchor = computed(() => props.today ?? new Date().toISOString().slice(0, 10))
const shift = (days: number) => {
  const d = new Date(`${anchor.value}T00:00:00Z`)
  return new Date(d.getTime() - days * 864e5).toISOString().slice(0, 10)
}

const presets = computed(() => [
  { label: 'Last 7 days', from: shift(6), to: anchor.value },
  { label: 'Last 30 days', from: shift(29), to: anchor.value },
  { label: 'Last 90 days', from: shift(89), to: anchor.value },
  { label: 'Year to date', from: `${anchor.value.slice(0, 4)}-01-01`, to: anchor.value },
])

function apply(p: { label: string; from: string; to: string }) {
  model.value = { ...p }
}

function setBound(which: 'from' | 'to', value: string) {
  model.value = { ...model.value, [which]: value, label: 'Custom' }
}
</script>

<template>
  <div class="flex flex-wrap items-end gap-2">
    <div class="flex flex-wrap gap-1">
      <button
        v-for="p in presets" :key="p.label"
        type="button"
        class="rounded-lg border px-3 py-2 text-meta transition-colors"
        :class="model.label === p.label
          ? 'border-accent bg-accent-soft font-medium text-accent-text'
          : 'border-border text-fg-muted hover:bg-bg-sunken'"
        @click="apply(p)"
      >{{ p.label }}</button>
    </div>

    <div class="flex items-center gap-1.5">
      <PhCalendarDots :size="16" class="shrink-0 text-fg-faint" />
      <input
        :value="model.from" type="date" aria-label="From date" :max="model.to"
        class="field min-h-[40px] w-[9.5rem] text-meta"
        @change="setBound('from', ($event.target as HTMLInputElement).value)"
      >
      <span class="text-meta text-fg-faint">to</span>
      <input
        :value="model.to" type="date" aria-label="To date" :min="model.from" :max="anchor"
        class="field min-h-[40px] w-[9.5rem] text-meta"
        @change="setBound('to', ($event.target as HTMLInputElement).value)"
      >
    </div>
  </div>
</template>
