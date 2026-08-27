<script setup lang="ts">
import type { ApiOrder } from '~/utils/orders'
import { deliveryProviderLabel, isAwaitingPayment } from '~/utils/orders'
import { ORDER_POLL_INTERVAL_MS, ORDER_POLL_MAX_ATTEMPTS } from '~/utils/constants'
import { formatMoney } from '~/utils/formatters'
import { whatsappMessage } from '~/utils/whatsapp'

/**
 * Order confirmation.
 *
 * SPA-only (nuxt.config.ts routeRules) — nothing here is crawlable, and it is
 * per-customer.
 *
 * `GET /api/v1/orders/{id}` has not been built yet (README Feature 4), so the
 * fetch below resolves to null rather than throwing: this app has no
 * `error.vue`, so an unhandled rejection on an SPA route is a blank page. The
 * three states — loading, found, unavailable — are all real states this page
 * will keep once the endpoint exists.
 *
 * Nothing navigates here yet either. `CheckoutPaymentStep` is this page's only
 * intended entry point and it is inert until the checkout session endpoint
 * lands, so today the page is reachable only by typing the URL.
 */
definePageMeta({ layout: 'default' })

const route = useRoute()
const config = useRuntimeConfig()

const orderUrl = `${config.public.apiBase}/orders/${route.params.id}`

const { data, pending, refresh } = await useAsyncData(`order-${route.params.id}`, () =>
  $fetch<{ data: ApiOrder }>(orderUrl).catch(() => null),
)

const order = computed(() => data.value?.data ?? null)
const unavailable = computed(() => !pending.value && !order.value)

const money = (minorUnits: number) =>
  order.value ? formatMoney(minorUnits, order.value.currency) : ''

/**
 * README Feature 4 edge case: the gateway webhook can arrive after the customer
 * is redirected here, so a freshly-placed order may still read `pending`. Poll
 * until it settles, then stop — bounded, because a webhook two minutes late is
 * an incident to investigate, not something to keep hammering the API over.
 */
const attempts = ref(0)
const stillConfirming = computed(
  () => !!order.value && isAwaitingPayment(order.value.status) && attempts.value < ORDER_POLL_MAX_ATTEMPTS,
)

let timer: ReturnType<typeof setInterval> | undefined

onMounted(() => {
  timer = setInterval(async () => {
    if (!stillConfirming.value) {
      clearInterval(timer)
      return
    }
    attempts.value += 1
    await refresh()
  }, ORDER_POLL_INTERVAL_MS)
})

onBeforeUnmount(() => clearInterval(timer))

useSeoMeta({
  title: 'Order confirmation — Gold Coast Tokota',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="page-gutter section-y mx-auto w-full max-w-[calc(48rem+120px)]">
    <!-- Loading -->
    <div v-if="pending" class="flex w-full flex-col items-start gap-4">
      <CommonSkeletonLoader height="2.5rem" width="60%" />
      <CommonSkeletonLoader height="1rem" width="40%" />
      <CommonSkeletonLoader height="12rem" width="100%" />
    </div>

    <!-- Endpoint absent, or no such order -->
    <div v-else-if="unavailable" class="flex w-full flex-col items-start gap-5">
      <h1 class="w-full text-display-section font-normal text-black">We can’t show this order</h1>
      <CommonInlineNotice variant="warning" title="Order lookup isn’t available yet">
        The orders endpoint hasn’t been built on the API side (README Feature 4), so we can’t
        load order <strong>{{ route.params.id }}</strong> right now. If you’ve placed an order,
        it isn’t lost — message us and we’ll confirm it by hand.
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
        <h1 class="w-full text-display-section font-normal text-black">Thank you — your order is in</h1>
        <p class="w-full text-body text-graphite">
          Order <strong class="font-normal">{{ order.reference || `#${order.id}` }}</strong>.
          We’ve emailed your confirmation.
        </p>
        <CommonStatusBadge :status="order.status" />
      </header>

      <!-- The brand guidelines promise "shipping updates via email or WhatsApp
           once dispatched", and this is the screen where someone asks. The
           mockup hides its floating button here; README Feature 6 requires it
           on every route, so it stays and this sits alongside it with the order
           number already in the message. -->
      <CommonWhatsAppLink
        source="order-tracking"
        :message="whatsappMessage.orderTracking(order.reference || `#${order.id}`)"
      >
        Track this order on WhatsApp
      </CommonWhatsAppLink>

      <CommonInlineNotice v-if="stillConfirming" title="Still confirming your payment">
        Your payment is going through. This page updates on its own — there’s no need to refresh
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
          <div class="flex w-full items-center justify-between">
            <dt class="text-graphite">Delivery</dt><dd class="text-black">{{ money(order.shipping_cost) }}</dd>
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
      <section class="flex w-full flex-col items-start gap-2 border-t border-line pt-6">
        <h2 class="w-full text-display-sm font-normal text-black">Delivery</h2>
        <p class="w-full text-body text-graphite">
          By {{ deliveryProviderLabel(order.delivery_provider) }}<template v-if="order.delivery_reference">
            · tracking {{ order.delivery_reference }}</template>.
        </p>
        <address v-if="order.shipping_address" class="w-full text-caption not-italic text-muted">
          {{ order.shipping_address.full_name }}<br>
          {{ order.shipping_address.line1 }}<br>
          {{ order.shipping_address.city }}<template v-if="order.shipping_address.region">, {{ order.shipping_address.region }}</template><br>
          {{ order.shipping_address.country }}
        </address>
      </section>

      <div class="flex w-full flex-col items-stretch gap-3 border-t border-line pt-6 sm:flex-row sm:items-start">
        <CommonBrandButton to="/shop">Keep shopping</CommonBrandButton>
        <CommonBrandButton to="/help/shipping" variant="white">Delivery help</CommonBrandButton>
      </div>
    </div>
  </div>
</template>
