<script setup lang="ts">
import { PhCaretLeft, PhCaretRight, PhX } from '@phosphor-icons/vue'

/**
 * Full-screen photo viewer, so a photo can be looked at properly without
 * leaving the admin for a new browser tab.
 *
 * `v-model:index` is the photo showing, or null when closed. Arrow keys and
 * the side buttons step through `images`; Esc, the close button or a click on
 * the backdrop closes it, and focus goes back to whatever opened it.
 */
export type LightboxImage = { src: string, alt: string, caption?: string, meta?: string }

const props = defineProps<{ images: LightboxImage[] }>()
const index = defineModel<number | null>('index', { default: null })

const open = computed(() => index.value !== null && !!props.images[index.value])
const current = computed(() => (index.value === null ? null : props.images[index.value] ?? null))
const closeButton = ref<HTMLButtonElement | null>(null)
let lastFocused: HTMLElement | null = null

useBodyScrollLock(open)

function close() {
  index.value = null
}

function step(by: number) {
  if (index.value === null || props.images.length < 2) return
  index.value = (index.value + by + props.images.length) % props.images.length
}

function onKeydown(e: KeyboardEvent) {
  if (!open.value) return
  if (e.key === 'Escape') close()
  else if (e.key === 'ArrowLeft') step(-1)
  else if (e.key === 'ArrowRight') step(1)
  else if (e.key === 'Tab') {
    // Keep focus inside while open.
    e.preventDefault()
    closeButton.value?.focus()
  }
}

watch(open, async (isOpen) => {
  if (isOpen) {
    lastFocused = document.activeElement as HTMLElement
    await nextTick()
    closeButton.value?.focus()
  } else {
    lastFocused?.focus()
  }
})

// A photo removed while the viewer is open.
watch(() => props.images.length, (length) => {
  if (index.value !== null && index.value >= length) index.value = length ? length - 1 : null
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
        v-if="open && current"
        class="fixed inset-0 z-modal flex flex-col bg-ink/90"
        role="dialog" aria-modal="true" :aria-label="current.alt"
        @click.self="close"
      >
        <div class="flex shrink-0 items-center gap-3 px-4 py-3 text-white">
          <p class="min-w-0 flex-1 truncate text-ui">
            {{ current.caption ?? current.alt }}
            <span v-if="images.length > 1" class="ml-2 text-meta text-white/60">
              {{ (index ?? 0) + 1 }} of {{ images.length }}
            </span>
          </p>
          <button
            ref="closeButton" type="button"
            class="rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white"
            aria-label="Close" @click="close"
          >
            <PhX :size="20" />
          </button>
        </div>

        <div class="relative flex min-h-0 flex-1 items-center justify-center px-4 pb-4 sm:px-16" @click.self="close">
          <img
            :key="current.src" :src="current.src" :alt="current.alt"
            class="max-h-full max-w-full rounded-md object-contain shadow-overlay"
          >
          <template v-if="images.length > 1">
            <button
              type="button"
              class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/40 p-2.5 text-white hover:bg-black/60 sm:left-4"
              aria-label="Previous photo" @click="step(-1)"
            >
              <PhCaretLeft :size="20" />
            </button>
            <button
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/40 p-2.5 text-white hover:bg-black/60 sm:right-4"
              aria-label="Next photo" @click="step(1)"
            >
              <PhCaretRight :size="20" />
            </button>
          </template>
        </div>

        <p v-if="current.meta" class="shrink-0 px-4 pb-4 text-center text-meta text-white/60">
          {{ current.meta }}
        </p>
      </div>
    </Transition>
  </Teleport>
</template>
