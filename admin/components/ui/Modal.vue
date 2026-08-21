<script setup lang="ts">
import { PhX } from '@phosphor-icons/vue'

/**
 * Modal dialog: scrim, focus trap, Esc to close, scroll lock, focus restored
 * to whatever opened it. Content scrolls inside a max-height container so a
 * long body never pushes the header and footer off-screen.
 */
const props = withDefaults(defineProps<{
  title?: string
  description?: string
  size?: 'sm' | 'md' | 'lg'
}>(), { size: 'md' })

const open = defineModel<boolean>('open', { default: false })
const panel = ref<HTMLElement | null>(null)
let lastFocused: HTMLElement | null = null

useBodyScrollLock(open)

const widths = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl' }

function focusables(): HTMLElement[] {
  if (!panel.value) return []
  return Array.from(panel.value.querySelectorAll<HTMLElement>(
    'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])',
  )).filter((el) => el.offsetParent !== null)
}

function onKeydown(e: KeyboardEvent) {
  if (!open.value) return
  if (e.key === 'Escape') { open.value = false; return }
  if (e.key !== 'Tab') return

  const items = focusables()
  if (!items.length) return
  const first = items[0]!
  const last = items[items.length - 1]!
  if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus() }
  else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus() }
}

watch(open, async (isOpen) => {
  if (isOpen) {
    lastFocused = document.activeElement as HTMLElement
    await nextTick()
    focusables()[0]?.focus()
  } else {
    lastFocused?.focus()
  }
})

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity" leave-active-class="transition-opacity"
      enter-from-class="opacity-0" leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-modal flex items-end justify-center bg-ink/40 p-0 sm:items-center sm:p-4"
        @click.self="open = false"
      >
        <div
          ref="panel"
          class="flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-lg bg-bg-elevated
                 shadow-overlay sm:rounded-lg"
          :class="widths[props.size]"
          role="dialog" aria-modal="true"
          :aria-label="title"
        >
          <div v-if="title" class="flex shrink-0 items-start gap-3 border-b border-border p-4 md:p-5">
            <div class="min-w-0 flex-1">
              <h2 class="text-section font-medium text-fg-strong">{{ title }}</h2>
              <p v-if="description" class="mt-1 text-meta text-fg-muted">{{ description }}</p>
            </div>
            <button type="button" class="toolbar-btn -mr-2 -mt-1 shrink-0" aria-label="Close" @click="open = false">
              <PhX :size="18" />
            </button>
          </div>

          <div class="min-h-0 flex-1 overflow-y-auto p-4 md:p-5">
            <slot />
          </div>

          <div v-if="$slots.footer" class="flex shrink-0 justify-end gap-2 border-t border-border p-4 md:p-5">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
