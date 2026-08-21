<script setup lang="ts">
import { PhShoppingCartSimple as ShoppingCartSimple } from '@phosphor-icons/vue'

// Customer photos pulled through for the launch build. Each tile carries a
// "shop this look" affordance, matching the cart icon button in the design.
const looks = [
  { id: 'look-1', image: '/design/ugc-1.png', alt: 'Customers wearing Gold Coast Tokota sandals at an event', to: '/shop' },
  { id: 'look-2', image: '/design/ugc-2.png', alt: 'Customer in Gold Coast Tokota sandals on a city street', to: '/shop' },
  { id: 'look-3', image: '/design/ugc-3.png', alt: 'Group wearing Gold Coast Tokota footwear', to: '/shop' },
  { id: 'look-4', image: '/design/ugc-4.png', alt: 'Customer styling Gold Coast Tokota sandals', to: '/shop' },
  { id: 'look-5', image: '/design/ugc-5.png', alt: 'Customer photo featuring Gold Coast Tokota sandals', to: '/shop' },
]

const { railEl, canScrollPrev, canScrollNext, scrollByPage } = useScrollRail()
</script>

<template>
  <section class="page-gutter section-y flex w-full flex-col items-center gap-3">
    <div class="flex w-full flex-col items-center gap-[25px] border-t border-graphite pt-12 text-center text-graphite lg:pt-[90px]">
      <h2 class="w-full text-display-md">Gold Coast Tokota Fits Well On You</h2>

      <div class="flex w-full flex-col items-center gap-1">
        <p class="w-full text-label">
          <span>Share your latest look with </span>
          <a
            href="https://www.instagram.com/explore/tags/goldcoasttokota/"
            target="_blank"
            rel="noopener noreferrer"
            class="underline hover:no-underline"
          >#GoldCoastTokota</a>
          <span> for a chance to be featured.</span>
        </p>
        <NuxtLink to="/community/submit" class="w-full text-label-link underline hover:no-underline">
          Add Your Photo
        </NuxtLink>
      </div>
    </div>

    <div class="flex w-full items-stretch gap-3 lg:gap-[18px]">
      <CommonCarouselArrow
        direction="left"
        class="hidden lg:flex"
        :disabled="!canScrollPrev"
        @click="scrollByPage(-1)"
      />

      <ul
        ref="railEl"
        class="flex flex-1 snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth lg:gap-[18px] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        <li
          v-for="look in looks"
          :key="look.id"
          class="relative flex h-[225px] w-[60%] shrink-0 snap-start items-start justify-end overflow-hidden p-2.5 sm:w-[40%] lg:w-[calc(20%-0.9rem)]"
        >
          <img :src="look.image" :alt="look.alt" class="absolute inset-0 size-full object-cover" loading="lazy">
          <NuxtLink
            :to="look.to"
            class="relative flex size-[30px] items-center justify-center rounded-full bg-white/90 text-graphite transition-colors hover:bg-white"
            aria-label="Shop this look"
          >
            <ShoppingCartSimple :size="16" />
          </NuxtLink>
        </li>
      </ul>

      <CommonCarouselArrow
        direction="right"
        class="hidden lg:flex"
        :disabled="!canScrollNext"
        @click="scrollByPage(1)"
      />
    </div>
  </section>
</template>
