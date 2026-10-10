<script setup lang="ts">
import { PhArrowRight as ArrowRight } from '@phosphor-icons/vue'
import type { MegaMenu } from '~/utils/navigation'

defineProps<{ menu: MegaMenu }>()
</script>

<template>
  <!-- In the home page's look: link columns under tracked-capital headings on
       the left, and on the right the promos as product-rail tiles (photo
       above, caption beneath in capitals) rather than white text reversed out
       over the photo. The inner row is held to the page's 1440px measure, so
       on a wide screen the columns line up with the content below. -->
  <div class="w-full border-y border-line bg-white">
    <div class="page-gutter mx-auto flex w-full max-w-[1440px] flex-col items-start gap-8 py-8 md:flex-row md:gap-10 lg:gap-16 lg:py-10">
      <div class="flex w-full min-w-0 gap-10 md:w-auto lg:gap-16">
        <div
          v-for="column in menu.columns"
          :key="column.heading"
          class="flex min-w-[160px] flex-col items-start gap-3"
        >
          <h3 class="caps-label w-full pb-1 text-muted">{{ column.heading }}</h3>
          <NuxtLink
            v-for="link in column.links"
            :key="link.label"
            :to="link.to"
            class="-my-2.5 flex min-h-[44px] w-full items-center py-2.5 text-label text-graphite underline-offset-4 transition-colors hover:text-ink hover:underline"
          >
            {{ link.label }}
          </NuxtLink>
        </div>
      </div>

      <div class="ml-auto grid w-full grid-cols-2 gap-1.5 md:max-w-[520px] lg:max-w-[560px]">
        <NuxtLink
          v-for="promo in menu.promos"
          :key="promo.label"
          :to="promo.to"
          class="group flex min-w-0 flex-col gap-3"
        >
          <img
            :src="promo.image"
            :alt="promo.alt"
            class="aspect-[4/3] w-full bg-surface object-cover"
            loading="lazy"
          >
          <span class="flex items-center justify-between gap-3 pr-1">
            <span class="caps-title min-w-0 group-hover:underline">{{ promo.label }}</span>
            <ArrowRight
              :size="16"
              class="shrink-0 text-ink motion-safe:transition-transform group-hover:translate-x-1"
              aria-hidden="true"
            />
          </span>
        </NuxtLink>
      </div>
    </div>
  </div>
</template>
