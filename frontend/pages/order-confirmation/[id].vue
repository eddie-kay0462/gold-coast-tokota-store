<script setup lang="ts">
import type { ApiOrder } from '~/utils/orders'
import { deliveryProviderLabel, isAwaitingPayment } from '~/utils/orders'
import { ORDER_POLL_INTERVAL_MS, ORDER_POLL_MAX_ATTEMPTS } from '~/utils/constants'
import { formatMoney } from '~/utils/formatters'
import { whatsappMessage } from '~/utils/whatsapp'
import { useCartStore } from '~/stores/cart'

/**
 * Order confirmation.
 *
 * SPA-only (nuxt.config.ts routeRules); nothing here is crawlable, and it is
 * per-customer.
 *
 * Paystack's `callback_url` lands here as `/order-confirmation/{reference}`,
 * the order *reference*, not its numeric id (`GET /orders/{reference}`).
 * The fetch resolves to null rather than throwing: this app has no
 * `error.vue`, so an unhandled rejection on an SPA route is a blank page.
 *
 * The cart is emptied only once the order reads paid. A customer who backs
 * out of Paystack lands here with a pending order and must still have their
 * basket to try again.
 */
definePageMeta({ layout: 'default' })

const route = useRoute()
const config = useRuntimeConfig()

const orderUrl = `${config.public.apiBase}/orders/${route.params.id}`

const { data, pending } = await useAsyncData(`order-${route.params.id}`, () =>
  $fetch<{ data: ApiOrder }>(orderUrl).catch(() => null),
)

const order = computed(() => data.value?.data ?? null)

/**
 * The skeleton is for the first load only. `pending` turns true again on
 * every refetch, and keying the template on it swapped the receipt for the
 * skeleton and back every five seconds while the page waited for payment.
 */
const firstLoad = computed(() => pending.value && !data.value)
const unavailable = computed(() => !pending.value && !order.value)

const money = (minorUnits: number) =>
  order.value ? formatMoney(minorUnits, order.value.currency) : ''

/**
 * README Feature 4 edge case: the gateway webhook can arrive after the customer
 * is redirected here, so a freshly-placed order may still read `pending`. Poll
 * until it settles, then stop. Bounded, because a webhook two minutes late is
 * an incident to investigate, not something to keep hammering the API over.
 */
const attempts = ref(0)
const stillConfirming = computed(
  () => !!order.value && isAwaitingPayment(order.value.status) && attempts.value < ORDER_POLL_MAX_ATTEMPTS,
)

let timer: ReturnType<typeof setTimeout> | undefined

/**
 * One poll. The server asks Paystack directly while the order reads pending,
 * so this settles as soon as the payment does, without waiting on the webhook.
 *
 * It writes the result only when the request succeeds: a dropped request
 * keeps the receipt on screen and tries again next time, rather than
 * flipping to "We can't show this order" for one interval. The next poll is
 * scheduled after this one finishes, so slow answers never stack up.
 */
async function poll() {
  if (!stillConfirming.value) return
  attempts.value += 1
  try {
    data.value = await $fetch<{ data: ApiOrder }>(orderUrl)
  } catch {
    // Keep what is on screen.
  }
  if (stillConfirming.value) timer = setTimeout(poll, ORDER_POLL_INTERVAL_MS)
}

onMounted(() => {
  timer = setTimeout(poll, ORDER_POLL_INTERVAL_MS)
})

onBeforeUnmount(() => clearTimeout(timer))

const cart = useCartStore()
const giftCard = useGiftCardCode()
/** A gift card bought online: no delivery, and the sandal cart is left alone. */
const isGiftCardOrder = computed(() => order.value?.kind === 'gift_card')
/** Places at a paid event: also no delivery and no cart. The ticket comes by email. */
const isBookingOrder = computed(() => order.value?.kind === 'booking')
const booking = computed(() => order.value?.booking ?? null)
const eventDate = computed(() => booking.value?.scheduled_date
  ? new Date(booking.value.scheduled_date).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  : null)
const giftCardRecipient = computed(() => order.value?.items[0]?.gift_card?.recipient_name ?? null)
watch(order, (o) => {
  if (!o || isAwaitingPayment(o.status) || o.status === 'cancelled' || o.kind === 'gift_card' || o.kind === 'booking') return
  cart.clear()
  giftCard.clear()
}, { immediate: true })

/** Polling gave up and the order is still unpaid; the payment did not go through. */
const notPaid = computed(() => !!order.value && isAwaitingPayment(order.value.status) && !stillConfirming.value)

useSeoMeta({
  title: 'Order confirmation | Gold Coast Tokota',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="page-gutter section-y mx-auto w-full max-w-[calc(48rem+120px)]">
    <!-- Loading -->
    <div v-if="firstLoad" class="flex w-full flex-col items-start gap-4">
      <CommonSkeletonLoader height="2.5rem" width="60%" />
      <CommonSkeletonLoader height="1rem" width="40%" />
      <CommonSkeletonLoader height="12rem" width="100%" />
    </div>

    <!-- Endpoint absent, or no such order -->
    <div v-else-if="unavailable" class="flex w-full flex-col items-start gap-5">
      <h1 class="w-full text-display-section font-normal text-black">We can’t show this order</h1>
      <CommonInlineNotice variant="warning" title="We couldn’t load this order">
        We couldn’t find order <strong>{{ route.params.id }}</strong>, or couldn’t reach our
        server just now. If you’ve paid, your order isn’t lost. Message us and we’ll confirm
        it by hand.
      </CommonInlineNotice>
      <div class="flex w-full flex-col items-stretch gap-3 sm:flex-row sm:items-start">
        <CommonWhatsAppLink
          source="order-help"
          variant="solid"
          :message="whatsappMessage.orderHelp(String(route.params.id))"
        >
          Message us about this order
        </CommonWhatsAppLink>
        <CommonBrandButton to="/shop" variant="white">Back to shop</CommonBrandButton>
      </div>
    </div>

    <!-- The receipt -->
    <div v-else-if="order" class="flex w-full flex-col items-start gap-8">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">
          {{ stillConfirming ? 'Confirming your payment…' : notPaid ? 'Your payment didn’t go through' : isGiftCardOrder ? 'Thank you! Your gift card is on its way' : isBookingOrder ? 'You’re booked! Your ticket is on its way' : 'Thank you! Your order is in' }}
        </h1>
        <p class="w-full text-body text-graphite">
          Order <strong class="font-normal">{{ order.reference || `#${order.id}` }}</strong>.
          <template v-if="!stillConfirming && !notPaid && isBookingOrder">
            We’ve emailed your ticket. Show it at the door, on your phone or printed.
          </template>
          <template v-else-if="!stillConfirming && !notPaid && isGiftCardOrder">
            We’ve emailed the gift card<template v-if="giftCardRecipient"> to {{ giftCardRecipient }}</template>, and your receipt to you.
          </template>
          <template v-else-if="!stillConfirming && !notPaid">We’ve emailed your confirmation.</template>
        </p>
        <CommonStatusBadge :status="order.status" />
      </header>

      <!-- The brand guidelines promise "shipping updates via email or WhatsApp
           once dispatched", and this is the screen where someone asks. The
           mockup hides its floating button here; README Feature 6 requires it
           on every route, so it stays and this sits alongside it with the order
           number already in the message. -->
      <!-- The event, and the ticket once the payment has confirmed the booking. -->
      <section v-if="isBookingOrder && booking && !notPaid" class="flex w-full flex-col items-start gap-3 border border-line p-4">
        <p class="caps-label text-muted">Your booking · {{ booking.reference }}</p>
        <p class="text-display-sm font-normal text-black">{{ booking.event }}</p>
        <p class="text-body text-graphite">
          <template v-if="eventDate">{{ eventDate }}</template><template v-if="booking.scheduled_slot"> · {{ booking.scheduled_slot }}</template><br>
          {{ booking.attendee_count }} {{ booking.attendee_count === 1 ? 'person' : 'people' }}<template v-if="booking.location_notes"> · {{ booking.location_notes }}</template>
        </p>
        <CommonBrandButton v-if="booking.ticket_url" :to="booking.ticket_url" target="_blank" rel="noopener">
          Download your ticket
        </CommonBrandButton>
        <p v-else-if="!stillConfirming" class="text-caption text-muted">
          Your ticket is being prepared and will be in your email in a moment.
        </p>
      </section>

      <CommonWhatsAppLink
        v-if="!isGiftCardOrder && !isBookingOrder"
        source="order-tracking"
        :message="whatsappMessage.orderTracking(order.reference || `#${order.id}`)"
      >
        Track this order on WhatsApp
      </CommonWhatsAppLink>

      <CommonInlineNotice v-if="notPaid" variant="warning" title="No payment received">
        We haven’t received payment for this order, so it hasn’t been placed and nothing has been
        charged.
        <template v-if="isGiftCardOrder">
          You can <NuxtLink to="/gift-cards" class="underline">try buying the gift card again</NuxtLink>,
          or message us on WhatsApp and we’ll help.
        </template>
        <template v-else-if="isBookingOrder">
          Your places were held for a short while; you can
          <NuxtLink to="/booking" class="underline">book again</NuxtLink>, or message us on WhatsApp and we’ll help.
        </template>
        <template v-else>
          Your cart is still saved, so you can
          <NuxtLink to="/checkout" class="underline">try checking out again</NuxtLink>, or message us
          on WhatsApp and we’ll help.
        </template>
      </CommonInlineNotice>

      <CommonInlineNotice v-if="stillConfirming" title="Still confirming your payment">
        Your payment is going through. This page updates on its own, so there’s no need to refresh
        or pay again.
      </CommonInlineNotice>

      <!-- Items -->
      <section class="flex w-full flex-col items-start gap-4">
        <h2 class="w-full text-display-sm font-normal text-black">What you ordered</h2>
        <ul class="flex w-full flex-col gap-4 border border-line p-4">
          <li v-for="item in order.items" :key="item.id" class="flex items-start gap-4">
            <img
              v-if="item.image"
              :src="item.image"
              :alt="item.name"
              class="h-[80px] w-[56px] shrink-0 object-cover"
              loading="lazy"
            >
            <div class="flex min-w-0 flex-1 flex-col">
              <NuxtLink v-if="item.slug" :to="`/shop/${item.slug}`" class="text-label text-black underline hover:no-underline">
                {{ item.name }}
              </NuxtLink>
              <span v-else class="text-label text-black">{{ item.name }}</span>
              <span class="text-caption text-muted">
                <template v-if="item.variant_label">{{ item.variant_label }} · </template>Qty {{ item.quantity }}
              </span>
            </div>
            <span class="shrink-0 whitespace-nowrap text-caption text-graphite">
              {{ money(item.unit_price * item.quantity) }}
            </span>
          </li>
        </ul>
      </section>

      <!-- Totals -->
      <section class="flex w-full flex-col items-start gap-2 border-t border-line pt-6">
        <h2 class="w-full text-display-sm font-normal text-black">Total</h2>
        <dl class="flex w-full flex-col gap-1 text-body">
          <div class="flex w-full items-center justify-between">
            <dt class="text-graphite">Subtotal</dt><dd class="text-black">{{ money(order.subtotal) }}</dd>
          </div>
          <div v-if="!isGiftCardOrder && !isBookingOrder" class="flex w-full items-center justify-between">
            <dt class="text-graphite">Delivery</dt><dd class="text-black">{{ money(order.shipping_cost) }}</dd>
          </div>
          <div v-if="order.gift_card_amount" class="flex w-full items-center justify-between">
            <dt class="text-graphite">
              Gift card<template v-if="order.gift_card_last_four"> ending {{ order.gift_card_last_four }}</template>
            </dt>
            <dd class="text-black">−{{ money(order.gift_card_amount) }}</dd>
          </div>
          <div v-if="order.tax" class="flex w-full items-center justify-between">
            <dt class="text-graphite">Tax</dt><dd class="text-black">{{ money(order.tax) }}</dd>
          </div>
          <div class="flex w-full items-center justify-between border-t border-line pt-2 font-normal">
            <dt class="text-black">Paid ({{ order.currency }})</dt>
            <dd class="text-black">{{ money(order.total) }}</dd>
          </div>
        </dl>
        <!-- Feature 4 requires the applied rate to be shown on USD orders, so
             the customer can reconcile the charge against the cedi price. -->
        <p v-if="order.currency === 'USD' && order.fx_rate_applied" class="w-full text-caption text-muted">
          Converted at ₵1 = ${{ order.fx_rate_applied.toFixed(4) }}, locked when you paid.
        </p>
      </section>

      <!-- Delivery -->
      <section v-if="!isGiftCardOrder && !isBookingOrder" class="flex w-full flex-col items-start gap-2 border-t border-line pt-6">
        <h2 class="w-full text-display-sm font-normal text-black">Delivery</h2>
        <p class="w-full text-body text-graphite">
          By {{ deliveryProviderLabel(order.delivery_provider, order.shipment?.courier) }}<template v-if="order.delivery_reference">
            · tracking {{ order.delivery_reference }}</template>.
        </p>
        <a
          v-if="order.shipment?.tracking_url"
          :href="order.shipment.tracking_url"
          target="_blank"
          rel="noopener"
          class="caps-label -my-2.5 flex min-h-[44px] items-center py-2.5 text-ink underline underline-offset-4 hover:no-underline"
        >
          Track your delivery
        </a>
        <address v-if="order.shipping_address" class="w-full text-caption not-italic text-muted">
          {{ order.shipping_address.full_name }}<br>
          {{ order.shipping_address.line1 }}<br>
          {{ order.shipping_address.city }}<template v-if="order.shipping_address.region">, {{ order.shipping_address.region }}</template><br>
          {{ order.shipping_address.country }}
        </address>
      </section>

      <div class="flex w-full flex-col items-stretch gap-3 border-t border-line pt-6 sm:flex-row sm:items-start">
        <CommonBrandButton to="/shop">Keep shopping</CommonBrandButton>
        <CommonBrandButton v-if="!isGiftCardOrder && !isBookingOrder" to="/help/shipping" variant="white">Delivery help</CommonBrandButton>
      </div>
    </div>
  </div>
</template>
