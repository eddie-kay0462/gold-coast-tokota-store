<script setup lang="ts">
import { PhArrowLeft as ArrowLeft, PhArrowRight as ArrowRight } from '@phosphor-icons/vue'

/**
 * The control row under a horizontal rail: arrows, a progress bar whose thumb
 * tracks the visible window, and an `n/N` count. After the Crockett & Jones
 * product rail the client gave as the reference for the home page.
 *
 * Presentational only. Pair it with `useScrollRail()`, which supplies every
 * prop, and wire `prev`/`next` to its `scrollByPage`.
 */
const props = defineProps<{
  canPrev: boolean
  canNext: boolean
  /** Fractions of the rail: `left` scrolled past, `width` on screen. */
  thumb: { left: number, width: number }
  /** Zero-based index of the leading slide. */
  current: number
  total: number
  /** What the rail holds, for the arrows' accessible names. */
  label: string
}>()

defineEmits<{ prev: [], next: [] }>()

/**
 * The last few slides can never reach the left edge — the rail runs out first —
 * so the leading-slide index stalls short of the total (7/10 on a wide screen).
 * At the end of the rail the count says so.
 */
const position = computed(() => {
  if (!props.total) return 0
  return props.canNext ? props.current + 1 : props.total
})
</script>

<template>
  <div class="flex w-full items-center gap-3 md:gap-5">
    <div class="-ml-3 flex shrink-0 items-center">
      <button
        type="button"
        class="flex size-11 items-center justify-center text-ink transition-opacity disabled:opacity-25"
        :disabled="!canPrev"
        :aria-label="`Previous ${label}`"
        @click="$emit('prev')"
      >
        <ArrowLeft :size="18" />
      </button>
      <button
        type="button"
        class="flex size-11 items-center justify-center text-ink transition-opacity disabled:opacity-25"
        :disabled="!canNext"
        :aria-label="`Next ${label}`"
        @click="$emit('next')"
      >
        <ArrowRight :size="18" />
      </button>
    </div>

    <!-- Decorative: the arrows and the count carry the same information. -->
    <div class="relative h-px min-w-0 flex-1 bg-line" aria-hidden="true">
      <span
        class="absolute -top-px h-[2px] bg-ink motion-safe:transition-[left] motion-safe:duration-150"
        :style="{ left: `${thumb.left * 100}%`, width: `${thumb.width * 100}%` }"
      />
    </div>

    <p class="shrink-0 text-caption tabular-nums text-graphite" aria-live="polite">
      <span class="sr-only">{{ label }} </span>{{ position }}/{{ total }}
    </p>
  </div>
</template>
