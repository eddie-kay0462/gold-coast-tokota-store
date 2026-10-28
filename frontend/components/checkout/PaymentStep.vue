<script setup lang="ts">
import type { ShippingAddress } from '~/components/checkout/CheckoutForm.vue'
import { addressPayload, cartLine, type ShippingQuote } from '~/composables/useShippingQuote'
import { useCartStore } from '~/stores/cart'
import { formatMoney } from '~/utils/formatters'
import { whatsappMessage } from '~/utils/whatsapp'
/**
 * The payment step.
 *
 * `placeOrder()` posts the cart to `POST /checkout/session`, which re-prices
 * every line, charges the delivery quote named by `quoteId`, locks the FX rate
 * for USD, reserves stock and
 * opens a Paystack session, then sends the customer to Paystack's hosted page.
 * Paystack returns them to `/order-confirmation/{reference}`, which waits for
 * the webhook to mark the order paid. Paystack handles both currencies (§13 of
 * GOLD_COAST_TOKOTA.md), so there is no client-side card form here.
 *
 * Without a Paystack key, a local API answers with its fake gateway, which
 * completes the same round trip; a production API refuses with a 503.
 *
 * Cart lines are sent as `{ slug, size, colour }`, not stock row ids: the cart keys
 * lines as `slug:size:colour` and never held real ids. The API resolves them.
 *
 * A gift card applied in the order summary goes with the order as
 * `gift_card_code`. If it covers everything, the API pays the order there and
 * then and hands back the confirmation page instead of Paystack's.
 */
const props = defineProps<{
  currency: 'GHS' | 'USD'
  totalGhs: number
  /** Live GHS→USD rate, or 0 when the FX endpoint hasn't responded. */
  fxRate: number
  address: ShippingAddress
  /** The delivery quote the customer saw, and the option they picked from it. */
  quoteId: string | null
  optionId: string | null
}>()

const emit = defineEmits<{ 'quote-changed': [ShippingQuote] }>()

const cart = useCartStore()
const api = useApi()
const giftCard = useGiftCardCode()

/** What's left for Paystack once any gift card is taken off (GHS minor units). */
const payableGhs = computed(() => props.totalGhs - giftCard.takenOff(props.totalGhs))
const coveredByGiftCard = computed(() => !!giftCard.applied.value && payableGhs.value === 0)

/**
 * The checkout handoff carries the whole basket, exactly like the cart
 * drawer's, the fallback for anyone who would rather order by message, or
 * whose payment cannot go through.
 */
/** The cart itself, saved under a code the team can open in the inbox. */
const whatsappOrderDraft = computed(() => ({ items: cart.items.map(cartLine), currency: props.currency }))

const whatsappOrderMessage = computed(() =>
  whatsappMessage.cart(
    cart.items.map((item) => {
      const variant = item.variantLabel ? ` (${item.variantLabel})` : ''
      return `• ${item.name}${variant} × ${item.quantity}`
    }),
    formatMoney(cart.subtotalGhs, 'GHS', { compact: true }),
  ),
)

const submitting = ref(false)
const notice = ref<{ title: string, body: string } | null>(null)

interface SessionError {
  statusCode?: number
  data?: { message?: string, line?: number | null, errors?: Record<string, string[]>, shipping_quote?: ShippingQuote }
}

function describeError(err: SessionError): { title: string, body: string } {
  const data = err.data ?? {}
  const lineName = (index: number | null | undefined) =>
    index != null && cart.items[index] ? `${cart.items[index].name}${cart.items[index].variantLabel ? ` (${cart.items[index].variantLabel})` : ''}` : null

  if (err.statusCode === 409) {
    const name = lineName(data.line)
    return {
      title: 'Something in your cart just sold out',
      body: name
        ? `${name} is no longer available in that quantity. Update your cart and try again.`
        : (data.message ?? 'An item in your cart is no longer available. Update your cart and try again.'),
    }
  }
  if (err.statusCode === 422 && data.errors?.gift_card_code) {
    return {
      title: 'Your gift card couldn’t be used',
      body: `${data.errors.gift_card_code[0]} We’ve taken it off. Place the order again to pay the full amount, or add another code.`,
    }
  }
  if (err.statusCode === 422) {
    const [field, messages] = Object.entries(data.errors ?? {})[0] ?? []
    const index = field?.match(/^items\.(\d+)\./)?.[1]
    const name = index !== undefined ? lineName(Number(index)) : null
    return {
      title: 'We couldn’t place this order',
      body: name
        ? `${name}: ${messages?.[0] ?? 'this item can’t be ordered.'} Remove it from your cart and try again.`
        : (messages?.[0] ?? data.message ?? 'Check your details and try again.'),
    }
  }
  if (err.statusCode === 503) {
    return { title: 'Payment is briefly unavailable', body: data.message ?? 'Please try again in a few minutes, or order on WhatsApp below.' }
  }
  if (!err.statusCode) {
    return { title: 'Couldn’t reach our server', body: 'Check your connection and try again. You haven’t been charged.' }
  }
  return { title: 'Something went wrong', body: 'Your order wasn’t placed and you haven’t been charged. Please try again, or order on WhatsApp below.' }
}

async function placeOrder() {
  notice.value = null
  submitting.value = true
  try {
    const res = await api<{ data: { reference: string }, payment: { authorization_url: string | null } }>(
      '/checkout/session',
      {
        method: 'POST',
        body: {
          items: cart.items.map(cartLine),
          currency: props.currency,
          shipping_quote_id: props.quoteId,
          shipping_option_id: props.optionId,
          shipping_address: addressPayload(props.address),
          gift_card_code: giftCard.applied.value?.code ?? null,
        },
      },
    )

    const url = res.payment.authorization_url
    if (!url) throw { statusCode: 500 } satisfies SessionError
    // A full navigation, not the router: Paystack's page is another origin.
    // The cart is kept until the confirmation page sees the order paid, so a
    // customer who backs out of Paystack still has their basket.
    window.location.assign(url)
  } catch (err: unknown) {
    const error = err as SessionError
    submitting.value = false
    // The delivery price moved since it was shown. Hand the fresh quote back
    // to the page, which returns to the delivery step to show it.
    if (error.statusCode === 409 && error.data?.shipping_quote) {
      emit('quote-changed', error.data.shipping_quote)
      return
    }
    notice.value = describeError(error)
    if (error.statusCode === 422 && error.data?.errors?.gift_card_code) giftCard.clear()
  }
}
</script>

<template>
  <div class="flex w-full flex-col items-start gap-5">
    <div class="flex w-full flex-col items-start gap-2 border-y border-line py-5">
      <p class="caps-label w-full text-muted">Paying in</p>
      <p class="w-full text-display-sm font-normal text-ink">
        <CommonPriceDisplay :base-price-ghs="payableGhs" compact />
      </p>
      <p v-if="coveredByGiftCard" class="w-full text-caption text-muted">
        Your gift card covers this order, so there’s nothing to pay.
      </p>
      <p v-else class="w-full text-caption text-muted">
        <template v-if="giftCard.applied.value">After your gift card. </template>
        Paid securely through Paystack, by card or mobile money. You’ll finish paying on
        Paystack’s page and come straight back here.
      </p>
    </div>

    <!-- Do not present a locked rate here; the lock happens server-side at
         session creation (README Feature 2/4). Saying it *will* be locked is
         accurate; showing a locked figure now would not be. -->
    <CommonInlineNotice v-if="currency === 'USD'" title="About the exchange rate">
      Your dollar total is converted from the cedi price<template v-if="fxRate"> at the current rate</template>.
      The rate is locked when you place the order, so the amount you’re charged matches the amount shown on Paystack.
    </CommonInlineNotice>

    <CommonInlineNotice v-if="notice" variant="warning" :title="notice.title" role="alert">
      {{ notice.body }}
    </CommonInlineNotice>

    <CommonBrandButton full :disabled="submitting" @click="placeOrder">
      {{ submitting ? (coveredByGiftCard ? 'Placing your order…' : 'Taking you to payment…') : 'Place order' }}
    </CommonBrandButton>

    <div class="flex w-full flex-col items-start gap-2 border-t border-line pt-5">
      <p class="w-full text-caption text-muted">
        Prefer to order the way you always have?
      </p>
      <CommonWhatsAppLink source="checkout" full :message="whatsappOrderMessage" :order-draft="whatsappOrderDraft">
        Continue on WhatsApp
      </CommonWhatsAppLink>
    </div>
  </div>
</template>
