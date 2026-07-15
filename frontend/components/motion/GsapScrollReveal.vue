<script setup lang="ts">
// Client-only GSAP wrapper — never referenced during SSR. Content is fully
// visible/readable by default; GSAP only progressively enhances on mount.
const rootEl = ref<HTMLElement | null>(null)

onMounted(async () => {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (prefersReducedMotion || !rootEl.value) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsap.from(rootEl.value.children, {
    opacity: 0,
    y: 40,
    stagger: 0.15,
    duration: 0.8,
    scrollTrigger: { trigger: rootEl.value, start: 'top 80%' },
  })
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
