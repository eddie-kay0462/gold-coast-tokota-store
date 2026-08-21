<script setup lang="ts">
import { PhRecycle as Recycle } from '@phosphor-icons/vue'

// Figma 13:1762 exports this band as a bitmap, clipped at both edges — the
// giveaway that it's a marquee. Built from live text instead so it stays
// crisp, readable to a screen reader, and actually moves.
const slogans = ['Keep it Clean', 'Do right by people', 'Keep It Local']
</script>

<template>
  <!-- Intentionally full-bleed: the marquee has to run off both edges, so it is
       one of the few sections that must not take `.page-gutter`. -->
  <section class="flex w-full items-center overflow-hidden bg-white py-12 lg:h-[225px] lg:py-0">
    <h2 class="sr-only">Keep it clean. Do right by people. Keep it local.</h2>

    <!-- Two identical runs so the loop has no visible seam. The second is
         hidden from assistive tech, which reads the heading above instead. -->
    <div class="ticker flex w-max shrink-0 items-center">
      <div v-for="run in 2" :key="run" class="flex shrink-0 items-center" :aria-hidden="run === 2 ? 'true' : undefined">
        <template v-for="slogan in slogans" :key="`${run}-${slogan}`">
          <span class="whitespace-nowrap px-4 text-display-md text-black lg:px-6 lg:text-display-heading">
            {{ slogan }}
          </span>
          <Recycle :size="40" weight="thin" class="shrink-0 text-black" aria-hidden="true" />
        </template>
      </div>
    </div>
  </section>
</template>

<style scoped>
.ticker {
  animation: ticker-scroll 30s linear infinite;
}

/* Each run is half the track, so translating by 50% lands exactly on the
   start of the second copy. */
@keyframes ticker-scroll {
  to {
    transform: translateX(-50%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .ticker {
    animation: none;
  }
}
</style>
