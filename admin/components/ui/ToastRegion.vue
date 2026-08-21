<script setup lang="ts">
import { PhCheckCircle, PhInfo, PhWarningCircle, PhX } from '@phosphor-icons/vue'

/**
 * The single toast region, mounted once in the layout. Bottom-right, stacking
 * upward — see the rationale in `composables/useToast.ts`.
 *
 * `aria-live="polite"` so a screen reader announces confirmations without
 * interrupting, and the region is always in the DOM (an element that appears
 * only when it has content is not reliably announced).
 */
const { toasts, dismiss } = useToast()

const icons = { success: PhCheckCircle, error: PhWarningCircle, info: PhInfo }
const tones = {
  success: 'border-success/30 bg-success-soft text-success',
  error: 'border-danger/30 bg-danger-soft text-danger',
  info: 'border-border bg-bg-elevated text-fg',
}
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-4 bottom-4 z-toast flex flex-col-reverse items-end gap-2
           sm:inset-x-auto sm:right-4 sm:w-80"
    role="status" aria-live="polite"
  >
    <TransitionGroup
      enter-active-class="transition" leave-active-class="transition absolute"
      enter-from-class="translate-y-2 opacity-0" leave-to-class="translate-y-2 opacity-0"
    >
      <div
        v-for="t in toasts" :key="t.id"
        class="pointer-events-auto flex w-full items-start gap-2.5 rounded-lg border px-3.5 py-3 shadow-popover"
        :class="tones[t.tone]"
      >
        <component :is="icons[t.tone]" :size="18" class="mt-px shrink-0" />
        <div class="min-w-0 flex-1">
          <p class="text-ui font-medium">{{ t.title }}</p>
          <p v-if="t.description" class="mt-0.5 text-meta opacity-90">{{ t.description }}</p>
        </div>
        <button
          type="button" class="-mr-1.5 -mt-1 shrink-0 rounded p-1 opacity-60 transition-opacity hover:opacity-100"
          :aria-label="`Dismiss: ${t.title}`" @click="dismiss(t.id)"
        >
          <PhX :size="14" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
