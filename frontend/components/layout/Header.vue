<script setup lang="ts">
import { PhArrowRight as ArrowRight, PhCaretDown as CaretDown, PhList as List, PhMagnifyingGlass as MagnifyingGlass, PhShoppingCartSimple as ShoppingCartSimple, PhUser as User, PhX as X } from '@phosphor-icons/vue'
import { useCartStore } from '~/stores/cart'
import { useCurrencyStore } from '~/stores/currency'
import { categoryNav, primaryNav } from '~/utils/navigation'

const currency = useCurrencyStore()
const cart = useCartStore()
const route = useRoute()

const mobileNavOpen = ref(false)
/** Search band under the nav rows (Figma 6:552). */
const searchOpen = ref(false)
const mobileExpanded = ref<string | null>(null)

/** Label of the category whose mega menu is open, or null when closed. */
const openMenu = ref<string | null>(null)
const triggerEls = new Map<string, HTMLButtonElement>()
let closeTimer: ReturnType<typeof setTimeout> | undefined

const openMenuItem = computed(() =>
  categoryNav.find((item) => item.label === openMenu.value),
)

function openMenuFor(label: string) {
  clearTimeout(closeTimer)
  openMenu.value = label
}

/** Small delay so moving the pointer between tab and panel doesn't flicker. */
function scheduleClose() {
  clearTimeout(closeTimer)
  closeTimer = setTimeout(() => (openMenu.value = null), 120)
}

function closeMenu(returnFocus = false) {
  clearTimeout(closeTimer)
  const label = openMenu.value
  openMenu.value = null
  if (returnFocus && label) triggerEls.get(label)?.focus()
}

function registerTrigger(label: string, el: unknown) {
  if (el) triggerEls.set(label, el as HTMLButtonElement)
  else triggerEls.delete(label)
}

// Any navigation closes both the mega menu and the mobile drawer.
watch(() => route.fullPath, () => {
  closeMenu()
  mobileNavOpen.value = false
  mobileExpanded.value = null
  searchOpen.value = false
})

onBeforeUnmount(() => clearTimeout(closeTimer))

/** Labels like "New Arrivals" must not become ids with spaces — aria-controls
 *  parses on whitespace, so a space would silently break the reference. */
function menuId(label: string) {
  return `mega-menu-${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`
}

function isActive(to: string) {
  return route.path === to.split('?')[0]?.split('#')[0]
}

function isCategoryActive(to: string) {
  const [path, query] = to.split('?')
  if (route.path !== path) return false
  if (!query) return true
  const [key, value] = query.split('=')
  return key ? route.query[key] === value : true
}
</script>

<template>
  <header class="relative z-50 bg-white" @keydown.esc="closeMenu(true); searchOpen = false">
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

    <!-- Primary nav. Three columns with equal 1fr flanks so the logo sits
         optically centred in the row regardless of how wide the nav or the
         icon cluster get. -->
    <div class="grid w-full grid-cols-[1fr_auto_1fr] items-center border-b border-line px-5 lg:px-[68px]">
      <div class="flex items-center justify-start">
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
      </div>

      <NuxtLink to="/" class="justify-self-center px-4 py-2" aria-label="Gold Coast Tokota — home">
        <img
          src="/brand/logo.png"
          alt="Gold Coast Tokota"
          class="h-6 w-auto lg:h-7"
          width="435"
          height="108"
        >
      </NuxtLink>

      <div class="flex items-center justify-end">
        <button
          type="button"
          class="flex items-center justify-center p-3"
          aria-label="Search"
          :aria-expanded="searchOpen"
          aria-controls="site-search"
          @click="searchOpen = !searchOpen"
        >
          <component :is="searchOpen ? X : MagnifyingGlass" :size="16" />
        </button>
        <NuxtLink to="/account" class="flex items-center justify-center p-3" aria-label="Account">
          <User :size="16" />
        </NuxtLink>
        <button
          type="button"
          class="relative flex items-center justify-center p-3"
          :aria-label="cart.itemCount ? `Cart, ${cart.itemCount} items` : 'Cart, empty'"
          @click="cart.openDrawer()"
        >
          <ShoppingCartSimple :size="16" />
          <span
            v-if="cart.itemCount"
            class="absolute right-1 top-1 flex size-4 items-center justify-center rounded-full bg-graphite text-[10px] leading-none text-white"
          >
            {{ cart.itemCount }}
          </span>
        </button>
      </div>
    </div>

    <!-- Category nav (desktop): items with a mega menu are buttons, the rest links -->
    <nav
      class="hidden w-full items-center justify-center lg:flex"
      aria-label="Categories"
      @mouseleave="scheduleClose"
    >
      <template v-for="item in categoryNav" :key="item.label">
        <button
          v-if="item.menu"
          :ref="(el) => registerTrigger(item.label, el)"
          type="button"
          class="flex shrink-0 flex-col items-start px-3 py-5 text-caption"
          :class="[
            item.accent ? 'text-sale' : 'text-graphite',
            openMenu === item.label || isCategoryActive(item.to) ? 'font-bold' : '',
          ]"
          :aria-expanded="openMenu === item.label"
          :aria-controls="menuId(item.label)"
          @mouseenter="openMenuFor(item.label)"
          @focus="openMenuFor(item.label)"
          @click="openMenu === item.label ? closeMenu() : openMenuFor(item.label)"
        >
          <span class="whitespace-nowrap">{{ item.label }}</span>
        </button>

        <NuxtLink
          v-else
          :to="item.to"
          class="flex shrink-0 flex-col items-start px-3 py-5 text-caption"
          :class="[
            item.accent ? 'text-sale' : 'text-graphite',
            isCategoryActive(item.to) ? 'font-bold' : '',
          ]"
          @mouseenter="scheduleClose"
          @focus="closeMenu()"
        >
          <span class="whitespace-nowrap">{{ item.label }}</span>
        </NuxtLink>
      </template>
    </nav>

    <LayoutSearchPanel id="site-search" :open="searchOpen" @close="searchOpen = false" />

    <!-- Mega menu panel: absolutely positioned so it overlays the page rather
         than pushing it down, matching the Figma frame. -->
    <Transition
      enter-active-class="motion-safe:transition-opacity motion-safe:duration-150"
      leave-active-class="motion-safe:transition-opacity motion-safe:duration-150"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="openMenuItem?.menu"
        :id="menuId(openMenuItem.label)"
        class="absolute inset-x-0 top-full z-50 hidden lg:block"
        @mouseenter="openMenuFor(openMenuItem!.label)"
        @mouseleave="scheduleClose"
      >
        <LayoutMegaMenuPanel :menu="openMenuItem.menu" />
      </div>
    </Transition>

    <!-- Mobile drawer: the mega menu becomes an inline accordion. -->
    <nav
      v-if="mobileNavOpen"
      id="mobile-nav"
      class="flex w-full flex-col border-t border-line lg:hidden"
      aria-label="Mobile navigation"
    >
      <NuxtLink
        v-for="item in primaryNav"
        :key="item.label"
        :to="item.to"
        class="px-5 py-4 text-caption text-graphite"
      >
        {{ item.label }}
      </NuxtLink>

      <template v-for="item in categoryNav" :key="item.label">
        <button
          v-if="item.menu"
          type="button"
          class="flex items-center justify-between px-5 py-4 text-left text-caption"
          :class="item.accent ? 'text-sale' : 'text-graphite'"
          :aria-expanded="mobileExpanded === item.label"
          @click="mobileExpanded = mobileExpanded === item.label ? null : item.label"
        >
          <span>{{ item.label }}</span>
          <CaretDown
            :size="14"
            class="transition-transform"
            :class="mobileExpanded === item.label ? 'rotate-180' : ''"
          />
        </button>

        <NuxtLink
          v-else
          :to="item.to"
          class="px-5 py-4 text-caption"
          :class="item.accent ? 'text-sale' : 'text-graphite'"
        >
          {{ item.label }}
        </NuxtLink>

        <div v-if="item.menu && mobileExpanded === item.label" class="flex flex-col bg-surface">
          <template v-for="column in item.menu.columns" :key="column.heading">
            <p class="px-8 pb-1 pt-4 text-eyebrow font-normal uppercase text-muted">
              {{ column.heading }}
            </p>
            <NuxtLink
              v-for="link in column.links"
              :key="link.label"
              :to="link.to"
              class="px-8 py-2.5 text-label text-graphite"
            >
              {{ link.label }}
            </NuxtLink>
          </template>
          <NuxtLink
            v-for="promo in item.menu.promos"
            :key="promo.label"
            :to="promo.to"
            class="relative m-4 flex h-[140px] items-end gap-3 overflow-hidden px-4 py-3"
          >
            <img :src="promo.image" :alt="promo.alt" class="absolute inset-0 size-full object-cover">
            <span class="relative flex-1 whitespace-pre-line text-body font-normal text-white">
              {{ promo.label }}
            </span>
            <ArrowRight :size="20" class="relative shrink-0 text-white" />
          </NuxtLink>
        </div>
      </template>
    </nav>
  </header>

  <!-- Page dimmer. Sits below the header/panel (z-50) but above page content. -->
  <Transition
    enter-active-class="motion-safe:transition-opacity motion-safe:duration-150"
    leave-active-class="motion-safe:transition-opacity motion-safe:duration-150"
    enter-from-class="opacity-0"
    leave-to-class="opacity-0"
  >
    <div
      v-if="openMenu"
      class="fixed inset-0 z-40 hidden bg-black/60 lg:block"
      aria-hidden="true"
      @click="closeMenu()"
    />
  </Transition>
</template>
