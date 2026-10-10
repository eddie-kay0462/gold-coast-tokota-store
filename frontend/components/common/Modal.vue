<script setup lang="ts">
import { PhX } from '@phosphor-icons/vue'

/**
 * A centred dialog with a close button in its top-right corner.
 *
 * Previously this was a bare `fixed inset-0 flex items-center` with no
 * max-height and no scroll container: anything taller than the viewport was
 * centred and unreachable in both directions, guaranteed on a 320×568 screen
 * with a form inside. It also had no backdrop gutter, so the panel ran
 * edge-to-edge on a phone, and no scroll lock or focus handling, unlike the
 * cart drawer.
 *
 * Teleported to `<body>`: a `position: sticky` ancestor (the product page's
 * buy panel, for one) creates a stacking context, and a modal rendered inside
 * it could not rise above the header however high its z-index.
 *
 * Closes on the × button, Escape, or a click on the backdrop. Tab stays inside
 * the dialog while it is open, and focus returns to whatever opened it.
 *
 * The z-index sits above the header and the WhatsApp button but below the cart
 * drawer, matching the scale documented in `layouts/default.vue`.
 */
const props = withDefaults(
  defineProps<{
    open: boolean
    title?: string
    /**
     * `md` for a short form or confirmation; `lg` for reference content like a
     * table. `lg` is capped at 640px tall so it reads as a popup over the page
     * rather than a second page; its body scrolls past that.
     */
    size?: 'md' | 'lg'
  }>(),
  { title: undefined, size: 'md' },
)
const emit = defineEmits<{ close: [] }>()

const panel = ref<HTMLElement | null>(null)
let previouslyFocused: HTMLElement | null = null

useBodyScrollLock(() => props.open)

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    emit('close')
    return
  }
  if (event.key !== 'Tab' || !panel.value) return

  // Keep Tab inside the dialog: wrap from the last control to the first and back.
  const focusable = [...panel.value.querySelectorAll<HTMLElement>(FOCUSABLE)]
  if (!focusable.length) return
  const first = focusable[0]!
  const last = focusable[focusable.length - 1]!
  const active = document.activeElement

  if (event.shiftKey && (active === first || active === panel.value)) {
    event.preventDefault()
    last.focus()
  }
  else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

watch(
  () => props.open,
  async (open) => {
    if (!import.meta.client) return
    if (open) {
      previouslyFocused = document.activeElement as HTMLElement
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      panel.value?.focus()
    }
    else {
      document.removeEventListener('keydown', onKeydown)
      previouslyFocused?.focus()
      previouslyFocused = null
    }
  },
)

onBeforeUnmount(() => {
  if (import.meta.client) document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[55] flex items-center justify-center overflow-y-auto bg-black/40 p-4"
        @click.self="emit('close')"
      >
        <div
          ref="panel"
          class="my-auto flex w-full flex-col overflow-hidden rounded-[2px] bg-white outline-none"
          :class="size === 'lg'
            ? 'max-h-[min(640px,calc(100dvh-2rem))] max-w-[960px]'
            : 'max-h-[calc(100dvh-2rem)] max-w-md'"
          role="dialog"
          aria-modal="true"
          :aria-label="title || undefined"
          tabindex="-1"
        >
          <header class="flex shrink-0 items-center justify-between gap-4 px-6 pb-3 pt-5 sm:px-8 sm:pt-6">
            <h2 v-if="title" class="text-display-sm font-normal text-black">{{ title }}</h2>
            <button
              type="button"
              class="-m-2.5 ml-auto flex size-11 shrink-0 items-center justify-center text-graphite transition-colors hover:text-black"
              :aria-label="title ? `Close ${title.toLowerCase()}` : 'Close'"
              @click="emit('close')"
            >
              <PhX :size="24" aria-hidden="true" />
            </button>
          </header>

          <!-- Only the body scrolls, so the title and close button stay put. -->
          <div class="min-h-0 flex-1 overflow-y-auto px-6 pb-6 sm:px-8">
            <slot />
          </div>

          <!-- Full-bleed strip under the body, e.g. a "still unsure?" line. -->
          <div v-if="$slots.footer" class="shrink-0">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
