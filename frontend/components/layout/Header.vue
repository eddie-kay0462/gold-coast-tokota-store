<script setup lang="ts">
import { PhArrowRight as ArrowRight, PhCaretDown as CaretDown, PhList as List, PhMagnifyingGlass as MagnifyingGlass, PhShoppingCartSimple as ShoppingCartSimple, PhUser as User, PhX as X } from '@phosphor-icons/vue'
import { useCartStore } from '~/stores/cart'
import { useCurrencyStore } from '~/stores/currency'
import { countryName, flagUrl } from '~/utils/geo'
import { categoryNav, primaryNav } from '~/utils/navigation'

const currency = useCurrencyStore()
// Flag reflects where the visitor is actually connecting from; resolved
// server-side and refined with browser signals a VPN doesn't change.
const { geo } = useVisitorCountry()
const cart = useCartStore()
const route = useRoute()

const mobileNavOpen = ref(false)

// The drawer is taller than a phone viewport once an accordion is expanded, so
// the page behind it must not scroll — same treatment as the cart drawer.
useBodyScrollLock(mobileNavOpen)
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

// The anchor is part of a tab's identity: two tabs can share a path and differ
// only by hash, and matching on path alone would light up both.
// The hash is never sent to the server, so until we're mounted only the
// anchorless tab can claim the underline — otherwise the client would disagree
// with the SSR markup it's hydrating.
const hashReady = ref(false)
onMounted(() => (hashReady.value = true))

function isActive(to: string) {
  const [pathAndQuery, hash] = to.split('#')
  if (route.path !== pathAndQuery?.split('?')[0]) return false
  if (!hashReady.value) return !hash
  return hash ? route.hash === `#${hash}` : !route.hash
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
  <!-- The chrome is dark from the approved mockup, and sticky as the mockup has
       it — the announcement strip travels with the nav rather than scrolling
       away, so the currency toggle and the cart stay reachable from anywhere on
       a page.

       The ground is set here, but the text colour and the light focus ring are
       set on each dark *row* rather than on this element, because the mega menu
       and search panels are light-on-white children of it.

       Anchor landings are handled by `scroll-padding-top` on `html`
       (`assets/css/main.css`), not by `scroll-mt` on every target: a sticky
       header covers the top of the viewport for every anchor on the site, so
       the offset belongs in one place. -->
  <header class="sticky top-0 z-50 bg-chrome" @keydown.esc="closeMenu(true); searchOpen = false">
    <!-- Announcement bar. The flag/currency cluster used to be absolutely
         positioned, so it reserved no width and the centred message ran
         underneath it on a phone (measured: the sign-up link overlapped it by
         41px at both 320px and 375px). It is a normal flex child now, and below
         `sm` the message scrolls through a marquee rather than wrapping.

         The strip is `ink` (#000) against the header's `chrome` (#111) — the
         same two-tone relationship the approved mockup draws. -->
    <div class="chrome-dark flex w-full items-center gap-3 bg-ink px-5 text-white sm:grid sm:grid-cols-[1fr_auto_1fr] lg:px-[30px]">
      <!-- Empty left flank. From `sm` this row is the same three-column grid the
           logo row below uses: two equal `1fr` flanks with the content in an
           `auto` track between them, so the message is centred against the *bar*
           rather than against whatever space the flag and currency cluster leave
           over. Below `sm` the flank collapses and the marquee takes the width. -->
      <span class="hidden sm:block" aria-hidden="true" />

      <LayoutAnnouncementBar />

      <div class="flex shrink-0 items-center justify-end gap-2.5">
        <img
          :key="geo.country"
          :src="flagUrl(geo.country)"
          :alt="countryName(geo.country)"
          :title="countryName(geo.country)"
          class="h-[15px] w-[21px] shrink-0 object-cover"
          width="21"
          height="15"
        >
        <CommonCurrencyToggle tone="dark" />
      </div>
    </div>

    <!-- Primary nav. Three columns with equal 1fr flanks so the logo sits
         optically centred in the row regardless of how wide the nav or the
         icon cluster get. -->
    <div class="chrome-dark grid w-full grid-cols-[1fr_auto_1fr] items-center border-b border-white/15 px-5 text-white md:px-10 lg:px-[68px]">
      <div class="flex items-center justify-start">
        <button
          type="button"
          class="p-3 md:hidden"
          :aria-expanded="mobileNavOpen"
          aria-controls="mobile-nav"
          aria-label="Toggle navigation"
          @click="mobileNavOpen = !mobileNavOpen"
        >
          <component :is="mobileNavOpen ? X : List" :size="20" />
        </button>

        <nav class="hidden items-start md:flex" aria-label="Primary">
          <NuxtLink
            v-for="item in primaryNav"
            :key="item.label"
            :to="item.to"
            class="flex flex-col items-start gap-[18px] px-3 pt-5"
            :class="isActive(item.to) ? '' : 'pb-5'"
          >
            <span class="whitespace-nowrap text-center text-caption text-white">{{ item.label }}</span>
            <span v-if="isActive(item.to)" class="h-0.5 w-full bg-gold" />
          </NuxtLink>
        </nav>
      </div>

      <NuxtLink to="/" class="flex min-h-[44px] items-center justify-self-center px-4" aria-label="Gold Coast Tokota — home">
        <img
          src="/brand/logo-white.png"
          alt="Gold Coast Tokota"
          class="h-6 w-auto md:h-7"
          width="435"
          height="108"
        >
      </NuxtLink>

      <div class="flex items-center justify-end">
        <button
          type="button"
          class="flex size-11 items-center justify-center"
          aria-label="Search"
          :aria-expanded="searchOpen"
          aria-controls="site-search"
          @click="searchOpen = !searchOpen"
        >
          <component :is="searchOpen ? X : MagnifyingGlass" :size="16" />
        </button>
        <NuxtLink to="/account" class="flex size-11 items-center justify-center" aria-label="Account">
          <User :size="16" />
        </NuxtLink>
        <button
          type="button"
          class="relative flex size-11 items-center justify-center"
          :aria-label="cart.itemCount ? `Cart, ${cart.itemCount} items` : 'Cart, empty'"
          @click="cart.openDrawer()"
        >
          <ShoppingCartSimple :size="16" />
          <span
            v-if="cart.itemCount"
            class="absolute right-1 top-1 flex size-4 items-center justify-center rounded-full bg-gold text-[10px] leading-none text-chrome"
          >
            {{ cart.itemCount }}
          </span>
        </button>
      </div>
    </div>

    <!-- Category nav (desktop): items with a mega menu are buttons, the rest links -->
    <nav
      class="chrome-dark hidden w-full items-center justify-center text-white md:flex"
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
            item.accent ? 'text-sale-on-dark' : 'text-white',
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
            item.accent ? 'text-sale-on-dark' : 'text-white',
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
        class="on-light absolute inset-x-0 top-full z-50 hidden md:block"
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
      class="chrome-dark flex max-h-[calc(100dvh-7rem)] w-full flex-col overflow-y-auto border-t border-white/15 text-white md:hidden"
      aria-label="Mobile navigation"
    >
      <NuxtLink
        v-for="item in primaryNav"
        :key="item.label"
        :to="item.to"
        class="px-5 py-4 text-caption text-white"
      >
        {{ item.label }}
      </NuxtLink>

      <template v-for="item in categoryNav" :key="item.label">
        <button
          v-if="item.menu"
          type="button"
          class="flex items-center justify-between px-5 py-4 text-left text-caption"
          :class="item.accent ? 'text-sale-on-dark' : 'text-white'"
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
          :class="item.accent ? 'text-sale-on-dark' : 'text-white'"
        >
          {{ item.label }}
        </NuxtLink>

        <div v-if="item.menu && mobileExpanded === item.label" class="on-light flex flex-col bg-surface">
          <template v-for="column in item.menu.columns" :key="column.heading">
            <p class="px-8 pb-1 pt-4 text-eyebrow font-normal uppercase text-muted">
              {{ column.heading }}
            </p>
            <NuxtLink
              v-for="link in column.links"
              :key="link.label"
              :to="link.to"
              class="flex min-h-[44px] items-center px-8 py-2.5 text-label text-graphite"
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
      class="fixed inset-0 z-40 hidden bg-black/60 md:block"
      aria-hidden="true"
      @click="closeMenu()"
    />
  </Transition>
</template>
