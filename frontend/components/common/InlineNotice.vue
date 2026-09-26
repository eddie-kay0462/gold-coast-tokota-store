<script setup lang="ts">
import { PhInfo, PhWarningCircle, PhCheckCircle } from '@phosphor-icons/vue'

/**
 * An in-flow notice panel.
 *
 * This exists rather than `Toast.vue` because the toast has no placement, no
 * shared region in `layouts/default.vue` and no consumer — where toasts appear
 * and how they stack is still an open decision (FOR_THE_TEAM.md, issue #10).
 * A notice that sits in the document needs none of that resolved.
 *
 * Its main job is carrying the "inert by design" explanation on forms whose
 * endpoint has not been built yet: the sign-in pages, the checkout payment
 * step, and the order lookup. Rendering the reason in place is what stops a
 * visitor — or the next developer — reading a dead form as a bug.
 */
withDefaults(
  defineProps<{
    variant?: 'info' | 'warning' | 'success'
    /** Optional bold lead-in above the message. */
    title?: string
  }>(),
  { variant: 'info' },
)

const icons = { info: PhInfo, warning: PhWarningCircle, success: PhCheckCircle }
</script>

<template>
  <!-- `role="note"` rather than `alert`: these are present on first paint and
       explain the page, so they must not interrupt a screen reader mid-task. -->
  <div
    role="note"
    class="flex w-full min-w-0 items-start gap-3 border px-4 py-3.5"
    :class="{
      'border-line bg-surface': variant === 'info',
      'border-sale/40 bg-sale/5': variant === 'warning',
      'border-line bg-white': variant === 'success',
    }"
  >
    <component
      :is="icons[variant]"
      :size="18"
      class="mt-px shrink-0"
      :class="variant === 'warning' ? 'text-sale' : 'text-subtle'"
      aria-hidden="true"
    />
    <div class="flex min-w-0 flex-1 flex-col gap-1 text-caption text-graphite">
      <p v-if="title" class="font-normal text-black">{{ title }}</p>
      <p class="min-w-0"><slot /></p>
      <!-- For notices that explain a dead end and can offer a way out of it —
           most often a WhatsApp handoff, since that is the channel that still
           works while checkout, discounts and order lookup are inert. -->
      <div v-if="$slots.action" class="mt-1 flex flex-wrap items-center gap-3">
        <slot name="action" />
      </div>
    </div>
  </div>
</template>
