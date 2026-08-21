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

    <!-- Two copies per run: one pass of the three slogans is ~1540px at the
         desktop size, which is narrower than a 1920px or 2560px viewport and
         would expose blank space before the loop restarts. -->
    <CommonMarquee :copies="2">
      <template v-for="slogan in slogans" :key="slogan">
        <span class="whitespace-nowrap px-4 text-display-heading text-black lg:px-6">
          {{ slogan }}
        </span>
        <Recycle :size="40" weight="thin" class="shrink-0 text-black" aria-hidden="true" />
      </template>
    </CommonMarquee>
  </section>
</template>
