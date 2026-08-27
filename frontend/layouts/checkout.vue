<script setup lang="ts">
import { useCartStore } from '~/stores/cart'

/**
 * Checkout chrome, modelled on a standard Shopify checkout: a single centred
 * logo, and nothing else to click away with.
 *
 * The storefront header and footer are deliberately absent. Shopify strips its
 * checkout for the reason every checkout is stripped — a nav bar at the payment
 * step is a row of exits — and the mega menu, search panel and eight footer
 * columns are all exits.
 *
 * Two things are kept that Shopify has no equivalent for:
 *   - `CartDrawer`, because "Return to cart" has to open something and this
 *     app's cart is a drawer, not a page.
 *   - `WhatsAppButton`, because README Feature 6's acceptance criteria say it is
 *     "visible and functional on every storefront route", and checkout is one.
 *     It is also the only way to actually complete an order today, while
 *     payment is inert.
 */
const cart = useCartStore()

const legalLinks = [
  { label: 'Refund policy', to: '/help/returns' },
  { label: 'Shipping policy', to: '/help/shipping' },
  { label: 'Privacy policy', to: '/legal/privacy' },
  { label: 'Terms of service', to: '/legal/terms' },
]
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-white">
    <header class="w-full border-b border-line">
      <div class="mx-auto flex w-full max-w-[1200px] items-center justify-center px-5 py-6 lg:px-10">
        <NuxtLink to="/" class="-my-2 flex min-h-[44px] items-center py-2" aria-label="Gold Coast Tokota — home">
          <img
            src="/brand/logo.png"
            alt="Gold Coast Tokota"
            class="h-7 w-auto"
            width="435"
            height="108"
          >
        </NuxtLink>
      </div>
    </header>

    <!-- `flex` so the page inside can stretch to the full height: the checkout's
         tinted summary column has to reach the footer, not stop at its content. -->
    <main class="flex min-w-0 flex-1 flex-col">
      <div class="flex flex-1 flex-col">
        <slot />
      </div>
    </main>

    <footer class="w-full border-t border-line">
      <div class="mx-auto flex w-full max-w-[1200px] flex-wrap items-center gap-x-6 px-5 py-6 lg:px-10">
        <NuxtLink
          v-for="link in legalLinks"
          :key="link.label"
          :to="link.to"
          class="-my-2 flex min-h-[44px] items-center py-2 text-caption text-muted underline hover:text-graphite"
        >
          {{ link.label }}
        </NuxtLink>
      </div>
    </footer>

    <LayoutWhatsAppButton />
    <CartDrawer />
  </div>
</template>
