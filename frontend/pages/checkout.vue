<script setup lang="ts">
import type { CheckoutStep } from '~/components/checkout/CheckoutSteps.vue'
import type { ShippingAddress } from '~/components/checkout/CheckoutForm.vue'
import { useCartStore } from '~/stores/cart'
import { useCurrencyStore } from '~/stores/currency'

// SPA-only route (see nuxt.config.ts routeRules) — no SEO requirement.
definePageMeta({ layout: 'default' })

const cart = useCartStore()
const currency = useCurrencyStore()

const step = ref<CheckoutStep>('details')
const summaryOpen = ref(false)

const address = reactive<ShippingAddress>({
  email: '',
  fullName: '',
  line1: '',
  city: '',
  region: '',
  postcode: '',
  country: 'GH',
  phone: '',
})

const deliveryMethod = ref<'standard' | 'express'>('standard')

function goTo(next: CheckoutStep) {
  step.value = next
  // The step content replaces the previous step in place, so without this the
  // viewport stays wherever the last button was.
  if (import.meta.client) window.scrollTo({ top: 0, behavior: 'smooth' })
}

useSeoMeta({
  title: 'Checkout — Gold Coast Tokota',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="page-gutter section-y mx-auto w-full max-w-[1190px]">
    <h1 class="text-display-section font-normal text-black">Checkout</h1>

    <!-- Nothing to pay for — send people back to the shop rather than showing
         an address form against an empty cart. -->
    <div v-if="cart.isEmpty" class="mt-6 flex flex-col items-start gap-4">
      <p class="text-body text-graphite">Your cart is empty.</p>
      <CommonBrandButton to="/shop">Shop Sandals</CommonBrandButton>
    </div>

    <div v-else class="mt-8 grid w-full grid-cols-1 gap-8 md:grid-cols-[1fr_360px] md:gap-12">
      <div class="flex min-w-0 flex-col items-start gap-8">
        <CheckoutSteps :current="step" @go="goTo" />

        <!-- Summary sits above the form on a phone, but collapsed — opening
             checkout on a wall of line items buries the first field. -->
        <div class="w-full md:hidden">
          <button
            type="button"
            class="flex min-h-[44px] w-full items-center justify-between border border-line px-4 py-3 text-left"
            :aria-expanded="summaryOpen"
            aria-controls="mobile-summary"
            @click="summaryOpen = !summaryOpen"
          >
            <span class="text-label text-graphite">
              {{ cart.itemCount }} {{ cart.itemCount === 1 ? 'item' : 'items' }}
            </span>
            <span class="flex items-center gap-2 text-label text-black">
              <CommonPriceDisplay :base-price-ghs="cart.subtotalGhs" compact />
              <span aria-hidden="true">{{ summaryOpen ? '−' : '+' }}</span>
            </span>
          </button>
          <div v-show="summaryOpen" id="mobile-summary" class="mt-3">
            <CheckoutCartSummary />
          </div>
        </div>

        <section v-if="step === 'details'" class="flex w-full flex-col items-start gap-5">
          <h2 class="w-full text-display-sm font-normal text-black">Delivery details</h2>
          <CheckoutForm v-model="address" @submit="goTo('delivery')" />
        </section>

        <section v-else-if="step === 'delivery'" class="flex w-full flex-col items-start gap-5">
          <h2 class="w-full text-display-sm font-normal text-black">Delivery method</h2>
          <CheckoutDeliveryOptions v-model="deliveryMethod" :country="address.country" />
          <div class="flex w-full flex-col items-stretch gap-3 sm:flex-row sm:items-start">
            <CommonBrandButton @click="goTo('payment')">Continue to payment</CommonBrandButton>
            <CommonBrandButton variant="white" @click="goTo('details')">Back</CommonBrandButton>
          </div>
        </section>

        <section v-else class="flex w-full flex-col items-start gap-5">
          <h2 class="w-full text-display-sm font-normal text-black">Payment</h2>
          <CheckoutPaymentStep
            :currency="currency.active"
            :total-ghs="cart.subtotalGhs"
            :fx-rate="currency.fxRate"
          />
          <CommonBrandButton variant="white" @click="goTo('delivery')">Back</CommonBrandButton>
        </section>
      </div>

      <aside class="hidden min-w-0 md:block">
        <div class="md:sticky md:top-6">
          <CheckoutCartSummary />
        </div>
      </aside>
    </div>
  </div>
</template>
