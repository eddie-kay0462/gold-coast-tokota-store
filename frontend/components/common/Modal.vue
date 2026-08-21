<script setup lang="ts">
/**
 * A centred dialog.
 *
 * Previously this was a bare `fixed inset-0 flex items-center` with no
 * max-height and no scroll container: anything taller than the viewport was
 * centred and unreachable in both directions — guaranteed on a 320×568 screen
 * with a form inside. It also had no backdrop gutter, so the panel ran
 * edge-to-edge on a phone, and no scroll lock or focus handling, unlike the
 * cart drawer.
 *
 * The z-index sits above the header and the WhatsApp button but below the cart
 * drawer, matching the scale documented in `layouts/default.vue`.
 */
const props = defineProps<{ open: boolean; title?: string }>()
const emit = defineEmits<{ close: [] }>()

const panel = ref<HTMLElement | null>(null)
let previouslyFocused: HTMLElement | null = null

useBodyScrollLock(() => props.open)

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') emit('close')
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
  <Transition name="fade">
    <div
      v-if="open"
      class="fixed inset-0 z-[55] flex items-center justify-center overflow-y-auto bg-black/40 p-4"
      @click.self="emit('close')"
    >
      <div
        ref="panel"
        class="my-auto flex max-h-[calc(100dvh-2rem)] w-full max-w-md flex-col overflow-y-auto rounded-lg bg-white p-6 outline-none"
        role="dialog"
        aria-modal="true"
        :aria-label="title || undefined"
        tabindex="-1"
      >
        <h2 v-if="title" class="mb-4 text-display-sm font-normal text-black">{{ title }}</h2>
        <slot />
      </div>
    </div>
  </Transition>
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
