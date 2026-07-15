<script setup lang="ts">
const rootEl = ref<HTMLElement | null>(null)

onMounted(async () => {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (prefersReducedMotion || !rootEl.value) return

  const { gsap } = await import('gsap')
  gsap.from(rootEl.value, { opacity: 0, y: 20, duration: 1, ease: 'power2.out' })
})
</script>

<template>
  <ClientOnly>
    <div ref="rootEl">
      <slot />
    </div>
    <template #fallback>
      <div><slot /></div>
    </template>
  </ClientOnly>
</template>
