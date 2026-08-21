<script setup lang="ts">
/**
 * Menu anchored to a trigger. Closes on Escape, on outside click, and after a
 * selection; arrow keys walk the items.
 *
 * Positioned with plain CSS rather than a floating-ui dependency — every menu
 * in this app hangs off a right-aligned toolbar button, so `right-0 top-full`
 * is correct and a positioning engine would be 12KB to solve a case we do not
 * have.
 */
withDefaults(defineProps<{ align?: 'left' | 'right'; label?: string }>(), { align: 'right' })

const open = ref(false)
const root = ref<HTMLElement | null>(null)

function close() { open.value = false }

function onKeydown(e: KeyboardEvent) {
  if (!open.value) return
  if (e.key === 'Escape') { close(); return }
  if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return
  e.preventDefault()
  const items = Array.from(root.value?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? [])
  if (!items.length) return
  const i = items.indexOf(document.activeElement as HTMLElement)
  const next = e.key === 'ArrowDown' ? (i + 1) % items.length : (i - 1 + items.length) % items.length
  items[next]?.focus()
}

function onPointerDown(e: PointerEvent) {
  if (open.value && root.value && !root.value.contains(e.target as Node)) close()
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('pointerdown', onPointerDown)
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('pointerdown', onPointerDown)
})
</script>

<template>
  <div ref="root" class="relative">
    <div :aria-expanded="open" aria-haspopup="menu" @click="open = !open">
      <slot name="trigger" :open="open" />
    </div>

    <Transition
      enter-active-class="transition" leave-active-class="transition"
      enter-from-class="-translate-y-1 opacity-0" leave-to-class="-translate-y-1 opacity-0"
    >
      <div
        v-if="open"
        class="absolute top-full z-popover mt-1 min-w-[12rem] rounded-lg bg-bg-elevated p-1 shadow-popover"
        :class="align === 'right' ? 'right-0' : 'left-0'"
        role="menu" :aria-label="label"
        @click="close"
      >
        <slot />
      </div>
    </Transition>
  </div>
</template>
