<script setup lang="ts">
import { PhArrowRight as ArrowRight } from '@phosphor-icons/vue'
import type { MegaMenu } from '~/utils/navigation'

defineProps<{ menu: MegaMenu }>()
</script>

<template>
  <div class="flex w-full flex-col items-start gap-8 border-t border-line bg-white px-5 pb-10 pt-8 md:flex-row md:gap-6 md:px-10 md:pb-14 md:pt-10 lg:px-[60px] lg:pb-[90px] lg:pt-[54px] xl:px-[140px]">
    <div
      v-for="column in menu.columns"
      :key="column.heading"
      class="flex min-w-0 flex-1 flex-col items-start gap-4"
    >
      <h3 class="w-full text-eyebrow font-normal uppercase text-muted">{{ column.heading }}</h3>
      <NuxtLink
        v-for="link in column.links"
        :key="link.label"
        :to="link.to"
        class="-my-2.5 flex min-h-[44px] w-full items-center py-2.5 text-label text-graphite hover:underline"
      >
        {{ link.label }}
      </NuxtLink>
    </div>

    <NuxtLink
      v-for="promo in menu.promos"
      :key="promo.label"
      :to="promo.to"
      class="group relative flex aspect-[4/3] min-w-0 flex-1 items-end gap-3 overflow-hidden px-4 py-3 lg:aspect-auto lg:h-[262px]"
    >
      <img :src="promo.image" :alt="promo.alt" class="absolute inset-0 size-full object-cover">
      <p class="relative min-w-0 flex-1 whitespace-pre-line text-[24px] font-normal leading-[33.24px] text-white">
        {{ promo.label }}
      </p>
      <ArrowRight
        :size="24"
        class="relative shrink-0 text-white transition-transform group-hover:translate-x-1"
      />
    </NuxtLink>
  </div>
</template>
