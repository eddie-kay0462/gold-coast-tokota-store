<script setup lang="ts">
import { PhArrowRight as ArrowRight, PhList as List, PhMagnifyingGlass as MagnifyingGlass, PhShoppingCartSimple as ShoppingCartSimple, PhUser as User, PhX as X } from '@phosphor-icons/vue'
import { useCurrencyStore } from '~/stores/currency'

const currency = useCurrencyStore()
const route = useRoute()

// Row 2 of the Figma header. `Sustainability` has no route in the spec's page
// map yet, so it points at the About page's sustainability anchor.
const primaryNav = [
  { label: 'News & Events', to: '/blog' },
  { label: 'Shop', to: '/shop' },
  { label: 'About', to: '/about' },
  { label: 'Sustainability', to: '/about#sustainability' },
]

// Row 3 — category entry points into the shop.
const categoryNav = [
  { label: 'Mens', to: '/shop?category=mens' },
  { label: 'Womens', to: '/shop?category=womens' },
  { label: 'Kids', to: '/shop?category=kids' },
  { label: 'New Arrivals', to: '/shop?sort=newest' },
  { label: 'Best-Sellers', to: '/shop?sort=best-selling' },
  { label: 'Merchandise', to: '/shop?category=merchandise' },
  { label: 'Custom Shoes', to: '/booking' },
  { label: 'Sale', to: '/shop?sale=true', accent: true },
]

const mobileNavOpen = ref(false)
watch(() => route.fullPath, () => (mobileNavOpen.value = false))

function isActive(to: string) {
  return route.path === to.split('?')[0]?.split('#')[0]
}
</script>

<template>
  <header class="bg-white">
    <!-- Announcement bar -->
    <div class="relative flex w-full items-center justify-center gap-1 bg-ink px-[30px] py-[7px]">
      <p class="text-center text-caption text-white">Get early access on launches and offers.</p>
      <NuxtLink to="/#newsletter" class="text-center text-caption text-white underline">
        Sign Up For Texts
      </NuxtLink>
      <ArrowRight :size="14" class="shrink-0 text-white" />

      <div class="absolute right-[30px] top-1/2 flex -translate-y-1/2 items-center gap-3">
        <img src="/design/flag.svg" alt="" class="h-[15px] w-[21px]" aria-hidden="true">
        <button
          type="button"
          class="text-caption text-white"
          :aria-label="`Currency: ${currency.active}. Switch to ${currency.active === 'GHS' ? 'USD' : 'GHS'}`"
          @click="currency.setCurrency(currency.active === 'GHS' ? 'USD' : 'GHS')"
        >
          {{ currency.active }}
        </button>
      </div>
    </div>

    <!-- Primary nav -->
    <div class="flex w-full items-center justify-between border-b border-line px-5 lg:px-[68px]">
      <button
        type="button"
        class="p-3 lg:hidden"
        :aria-expanded="mobileNavOpen"
        aria-controls="mobile-nav"
        aria-label="Toggle navigation"
        @click="mobileNavOpen = !mobileNavOpen"
      >
        <component :is="mobileNavOpen ? X : List" :size="20" />
      </button>

      <nav class="hidden items-start lg:flex" aria-label="Primary">
        <NuxtLink
          v-for="item in primaryNav"
          :key="item.label"
          :to="item.to"
          class="flex flex-col items-start gap-[18px] px-3 pt-5"
          :class="isActive(item.to) ? '' : 'pb-5'"
        >
          <span class="whitespace-nowrap text-center text-caption text-graphite">{{ item.label }}</span>
          <span v-if="isActive(item.to)" class="h-0.5 w-full bg-graphite" />
        </NuxtLink>
      </nav>

      <NuxtLink to="/" class="text-caption uppercase tracking-[1.4px] lg:hidden">
        Gold Coast Tokota
      </NuxtLink>

      <div class="flex items-center justify-end">
        <button type="button" class="flex items-center justify-center p-3" aria-label="Search">
          <MagnifyingGlass :size="16" />
        </button>
        <NuxtLink to="/account" class="flex items-center justify-center p-3" aria-label="Account">
          <User :size="16" />
        </NuxtLink>
        <NuxtLink to="/checkout" class="flex items-center justify-center p-3" aria-label="Cart">
          <ShoppingCartSimple :size="16" />
        </NuxtLink>
      </div>
    </div>

    <!-- Category nav -->
    <nav
      id="mobile-nav"
      class="w-full items-center justify-center overflow-x-auto lg:flex lg:flex-row"
      :class="mobileNavOpen ? 'flex flex-col' : 'hidden'"
      aria-label="Categories"
    >
      <NuxtLink
        v-for="item in [...primaryNav, ...categoryNav]"
        :key="item.label"
        :to="item.to"
        class="flex shrink-0 flex-col items-start px-3 py-5"
        :class="[
          'accent' in item && item.accent ? 'text-sale' : 'text-graphite',
          primaryNav.includes(item as never) ? 'lg:hidden' : '',
        ]"
      >
        <span class="whitespace-nowrap text-center text-caption">{{ item.label }}</span>
      </NuxtLink>
    </nav>
  </header>
</template>
