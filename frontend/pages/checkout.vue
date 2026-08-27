<script setup lang="ts">
import { PhCaretDown, PhCaretLeft, PhLockSimple } from '@phosphor-icons/vue'
import type { CheckoutStep } from '~/components/checkout/CheckoutSteps.vue'
import type { ShippingAddress } from '~/components/checkout/CheckoutForm.vue'
import { useCartStore } from '~/stores/cart'
import { useCurrencyStore } from '~/stores/currency'

/**
 * Checkout, laid out as a standard Shopify checkout.
 *
 * The shape is the recognisable one: stripped chrome (see `layouts/checkout`),
 * a `Cart › Information › Shipping › Payment` breadcrumb, the form in a
 * measured column on the left, and the order summary in a tinted panel that
 * bleeds to the right edge of the viewport. Below `lg` the summary collapses
 * into the "Show order summary" bar Shopify puts above everything.
 *
 * It stays a three-step flow rather than Shopify's newer one-page checkout,
 * because the step machinery here is real — the information step validates
 * before it will advance, and collapsing it would mean either throwing that
 * away or validating a whole page at once.
 */
definePageMeta({ layout: 'checkout' })

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

const isGhana = computed(() => address.country === 'GH')
const provider = computed(() => (isGhana.value ? 'Yango' : 'DHL'))

/**
 * What the summary's Shipping row says.
 *
 * Never a number. The real figure comes from the courier quote at
 * checkout-session creation, which is not built — and putting a guess in the
 * Total is how someone ends up surprised at the payment screen. Shopify shows
 * "Calculated at next step" for the same reason before an address is entered.
 */
const deliveryLabel = computed(() => {
  if (step.value === 'details') return 'Calculated at the next step'
  return `${provider.value} · quoted at payment`
})

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
  <!-- Nothing to pay for — send people back to the shop rather than showing an
       address form against an empty cart. -->
  <div v-if="cart.isEmpty" class="mx-auto flex w-full max-w-[560px] flex-col items-start gap-4 px-5 py-20">
    <h1 class="text-display-sm font-normal text-black">Your cart is empty</h1>
    <p class="text-body text-graphite">Nothing to check out yet.</p>
    <CommonBrandButton to="/shop">Shop Sandals</CommonBrandButton>
  </div>

  <div v-else class="flex flex-1 flex-col">
    <!-- Mobile summary bar. Above everything, collapsed, exactly where Shopify
         puts it: opening checkout on a wall of line items buries the first
         field, but hiding the total entirely is worse. -->
    <div class="border-b border-line bg-surface lg:hidden">
      <div class="mx-auto w-full max-w-[560px] px-5">
        <button
          type="button"
          class="flex min-h-[56px] w-full items-center justify-between gap-4 text-left"
          :aria-expanded="summaryOpen"
          aria-controls="mobile-summary"
          @click="summaryOpen = !summaryOpen"
        >
          <span class="flex items-center gap-1.5 text-caption text-graphite">
            {{ summaryOpen ? 'Hide' : 'Show' }} order summary
            <PhCaretDown
              :size="12"
              class="transition-transform"
              :class="summaryOpen ? 'rotate-180' : ''"
              aria-hidden="true"
            />
          </span>
          <CommonPriceDisplay
            class="text-body font-normal text-black"
            :base-price-ghs="cart.subtotalGhs"
            compact
          />
        </button>

        <div v-show="summaryOpen" id="mobile-summary" class="border-t border-line py-6">
          <CheckoutOrderSummary :delivery-ghs="null" :delivery-label="deliveryLabel" />
        </div>
      </div>
    </div>

    <!-- Two columns from `lg`. The summary column carries its own ground and
         bleeds to the right edge of the viewport, which is the single most
         recognisable thing about a Shopify checkout — so the tint sits on the
         grid cell and the content inside it is what gets measured. -->
    <!-- `lg:flex-1` so the summary's tint reaches the footer even on the payment
         step, where the left column is short. Stretched with flex rather than
         `min-h-full`: a percentage height against a flex-sized ancestor is not
         reliably resolvable, and this chain is flex the whole way up from
         `layouts/checkout`. -->
    <div class="lg:grid lg:flex-1 lg:grid-cols-2">
      <div class="flex justify-center lg:justify-end">
        <div class="w-full max-w-[560px] px-5 py-8 lg:py-14 lg:pr-14">
          <CheckoutSteps :current="step" @go="goTo" @cart="cart.openDrawer()" />

          <div class="mt-8">
            <CheckoutForm
              v-if="step === 'details'"
              v-model="address"
              @submit="goTo('delivery')"
              @cart="cart.openDrawer()"
            />

            <div v-else-if="step === 'delivery'" class="flex w-full flex-col items-start gap-8">
              <!-- Shopify repeats the entered details above the shipping choice
                   as a bordered review block with "Change" links. It is the
                   step's only reassurance that the address it is quoting for is
                   the right one. -->
              <dl class="w-full divide-y divide-line rounded border border-line text-caption">
                <div class="flex items-start gap-4 px-4 py-3">
                  <dt class="w-16 shrink-0 text-muted">Contact</dt>
                  <dd class="min-w-0 flex-1 break-words text-graphite">{{ address.email }}</dd>
                  <button type="button" class="shrink-0 text-graphite underline" @click="goTo('details')">
                    Change
                  </button>
                </div>
                <div class="flex items-start gap-4 px-4 py-3">
                  <dt class="w-16 shrink-0 text-muted">Ship to</dt>
                  <dd class="min-w-0 flex-1 break-words text-graphite">
                    {{ [address.line1, address.city, address.region, address.postcode].filter(Boolean).join(', ') }}
                  </dd>
                  <button type="button" class="shrink-0 text-graphite underline" @click="goTo('details')">
                    Change
                  </button>
                </div>
              </dl>

              <div class="flex w-full flex-col items-start gap-4">
                <h2 class="w-full text-body font-normal text-black">Shipping method</h2>
                <CheckoutDeliveryOptions v-model="deliveryMethod" :country="address.country" />
              </div>

              <div class="flex w-full flex-col-reverse items-stretch gap-4 sm:flex-row sm:items-center sm:justify-between">
                <button
                  type="button"
                  class="-my-2.5 flex min-h-[44px] items-center gap-1 py-2.5 text-caption text-graphite hover:underline"
                  @click="goTo('details')"
                >
                  <PhCaretLeft :size="12" aria-hidden="true" />
                  Return to information
                </button>
                <CommonBrandButton @click="goTo('payment')">Continue to payment</CommonBrandButton>
              </div>
            </div>

            <div v-else class="flex w-full flex-col items-start gap-8">
              <div class="flex w-full flex-col items-start gap-2">
                <h2 class="w-full text-body font-normal text-black">Payment</h2>
                <p class="flex items-center gap-1.5 text-caption text-muted">
                  <PhLockSimple :size="12" aria-hidden="true" />
                  All transactions are secure and encrypted.
                </p>
              </div>

              <CheckoutPaymentStep
                :currency="currency.active"
                :total-ghs="cart.subtotalGhs"
                :fx-rate="currency.fxRate"
              />

              <button
                type="button"
                class="-my-2.5 flex min-h-[44px] items-center gap-1 py-2.5 text-caption text-graphite hover:underline"
                @click="goTo('delivery')"
              >
                <PhCaretLeft :size="12" aria-hidden="true" />
                Return to shipping
              </button>
            </div>
          </div>
        </div>
      </div>

      <aside class="hidden lg:block lg:border-l lg:border-line lg:bg-surface">
        <div class="sticky top-0 w-full max-w-[520px] px-5 py-14 lg:pl-14">
          <CheckoutOrderSummary :delivery-ghs="null" :delivery-label="deliveryLabel" />
        </div>
      </aside>
    </div>
  </div>
</template>
