<script setup lang="ts">
/**
 * A seamless horizontal marquee.
 *
 * Two identical runs sit side by side in a `w-max` track; the animation
 * translates by exactly 50%, which lands on the start of the second run, so the
 * loop has no visible seam. The track must therefore be at least twice the
 * viewport wide or blank space appears before the loop restarts — that is what
 * `copies` is for: it repeats the slot content within each run until the run
 * alone exceeds the widest viewport the page supports.
 *
 * Only the first copy is exposed to assistive tech; every duplicate is
 * `aria-hidden`, so a screen reader hears the content once. Consumers that hide
 * meaning in the motion should still provide their own `sr-only` heading.
 */
withDefaults(
  defineProps<{
    /** Repeats of the slot content inside each run. Raise until one run is wider than the viewport. */
    copies?: number
    /** Seconds for one full pass. Longer = slower. */
    duration?: number
  }>(),
  { copies: 1, duration: 30 },
)
</script>

<template>
  <div class="flex w-full items-center overflow-hidden">
    <div
      class="marquee-track flex w-max shrink-0 items-center"
      :style="{ '--marquee-duration': `${duration}s` }"
    >
      <div
        v-for="run in 2"
        :key="run"
        class="flex shrink-0 items-center"
        :aria-hidden="run === 2 ? 'true' : undefined"
      >
        <div
          v-for="copy in copies"
          :key="copy"
          class="flex shrink-0 items-center"
          :aria-hidden="copy > 1 ? 'true' : undefined"
        >
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.marquee-track {
  animation: marquee-scroll var(--marquee-duration, 30s) linear infinite;
}

/* Each run is half the track, so translating by 50% lands exactly on the
   start of the second copy. */
@keyframes marquee-scroll {
  to {
    transform: translateX(-50%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .marquee-track {
    animation: none;
  }
}
</style>
