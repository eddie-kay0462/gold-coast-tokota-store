<script setup lang="ts">
/**
 * Hover/focus label, used mainly by the collapsed sidebar rail where an icon
 * is the only affordance.
 *
 * Shown on focus as well as hover — a keyboard user reaching an icon-only
 * button needs the label just as much as a mouse user, and `title` alone is
 * not announced consistently. The trigger still carries `aria-label`, so the
 * tooltip is purely visual and marked `aria-hidden`.
 */
withDefaults(defineProps<{ label: string; placement?: 'right' | 'top' }>(), { placement: 'right' })
const shown = ref(false)
</script>

<template>
  <div
    class="relative flex"
    @mouseenter="shown = true" @mouseleave="shown = false"
    @focusin="shown = true" @focusout="shown = false"
  >
    <slot />
    <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
      <span
        v-if="shown"
        class="pointer-events-none absolute z-popover whitespace-nowrap rounded bg-fg-strong px-2 py-1
               text-micro text-bg shadow-popover"
        :class="placement === 'right'
          ? 'left-full top-1/2 ml-2 -translate-y-1/2'
          : 'bottom-full left-1/2 mb-2 -translate-x-1/2'"
        aria-hidden="true"
      >{{ label }}</span>
    </Transition>
  </div>
</template>
