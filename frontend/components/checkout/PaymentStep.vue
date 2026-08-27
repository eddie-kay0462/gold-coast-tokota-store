<script setup lang="ts">
/**
 * The payment step — THE ONE INERT BOUNDARY IN CHECKOUT.
 *
 * Everything before this point is real: the address validates, the totals are
 * live, the currency routing below is the rule README Feature 4 specifies.
 * What does not exist is `POST /api/v1/checkout/session` — no CheckoutController,
 * no Paystack or Stripe service, no webhook receiver. So `placeOrder()`
 * simulates the loading state and then explains itself, rather than firing a
 * request that would 404.
 *
 * When the endpoint lands, `placeOrder()` becomes:
 *   POST /checkout/session { items, currency, shipping_address, delivery_method }
 *   → GHS: redirect to the returned Paystack authorization URL
 *   → USD: confirm the returned Stripe PaymentIntent client secret
 *   → gateway redirects back to /order-confirmation/{id}
 * Nothing else in this component changes.
 *
 * Note that `/order-confirmation/[id]` is built and waiting, but nothing can
 * reach it until then — it has no other entry point in the app.
 */
const props = defineProps<{
  currency: 'GHS' | 'USD'
  totalGhs: number
  /** Live GHS→USD rate, or 0 when the FX endpoint hasn't responded. */
  fxRate: number
}>()

const { href: whatsappHref } = useWhatsApp()

const gateway = computed(() => (props.currency === 'GHS' ? 'Paystack' : 'Stripe'))

const submitting = ref(false)
const notice = ref<string | null>(null)

async function placeOrder() {
  notice.value = null
  submitting.value = true
  await new Promise((resolve) => setTimeout(resolve, 600))
  submitting.value = false
  notice.value =
    'Payment isn’t enabled yet. The checkout session endpoint hasn’t been built on the API side ' +
    '(README Feature 4), so this step is inactive. Everything above it is real — your address ' +
    'validates and the totals are live. To order today, message us on WhatsApp.'
}
</script>

<template>
  <div class="flex w-full flex-col items-start gap-5">
    <div class="flex w-full flex-col items-start gap-2 border border-line p-5">
      <p class="w-full text-caption text-muted">Paying in</p>
      <p class="w-full text-display-sm font-normal text-black">
        <CommonPriceDisplay :base-price-ghs="totalGhs" compact />
      </p>
      <p class="w-full text-caption text-muted">
        Processed securely by {{ gateway }}.
        <template v-if="currency === 'GHS'">Cedi payments are handled by Paystack.</template>
        <template v-else>Dollar payments are handled by Stripe.</template>
      </p>
    </div>

    <!-- Do not present a locked rate here — the lock happens server-side at
         session creation (README Feature 2/4). Saying it *will* be locked is
         accurate; showing a locked figure now would not be. -->
    <CommonInlineNotice v-if="currency === 'USD'" title="About the exchange rate">
      Your dollar total is converted from the cedi price<template v-if="fxRate"> at the current rate</template>.
      The rate is locked when payment begins, so the amount you’re charged matches the amount shown here.
    </CommonInlineNotice>

    <CommonInlineNotice v-if="notice" variant="warning" title="Payment isn’t enabled yet">
      {{ notice }}
    </CommonInlineNotice>

    <CommonBrandButton full :disabled="submitting" @click="placeOrder">
      {{ submitting ? 'Placing order…' : 'Place order' }}
    </CommonBrandButton>

    <div v-if="whatsappHref" class="flex w-full flex-col items-start gap-2 border-t border-line pt-5">
      <p class="w-full text-caption text-muted">
        Prefer to order the way you always have?
      </p>
      <CommonBrandButton :to="whatsappHref" variant="white" full>
        Continue on WhatsApp
      </CommonBrandButton>
    </div>
  </div>
</template>
