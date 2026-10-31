<script setup lang="ts">
import { PhChatsTeardrop, PhTruck, PhX } from '@phosphor-icons/vue'
import type { Order, OrderStatus, Shipment } from '~/types'
import { PAYMENT_METHOD_LABELS } from '~/types'
import { formatMoney } from '~/utils/currency'

/**
 * Order detail drawer: README Feature 9 specifies a drawer rather than a
 * page, so working through a queue keeps your place in the list.
 */
const props = defineProps<{ order: Order | null }>()
const open = defineModel<boolean>('open', { default: false })
/** A shipment action changed the order's status too; the list should refetch. */
const emit = defineEmits<{ changed: [] }>()

// Held locally so an action's result shows at once, without waiting for the
// list behind the drawer to refetch.
const shipment = ref<Shipment | null>(null)
watch(() => props.order?.shipment, (value) => { shipment.value = value ?? null }, { immediate: true })

// List rows carry the shipment without its history; fetch that on open.
const { adminFetch } = useAdminApi()
watch([open, () => shipment.value?.id], async ([isOpen, id]) => {
  if (!isOpen || !id || shipment.value?.events?.length) return
  try {
    const { data } = await adminFetch<Shipment>(`/admin/shipments/${id}`)
    if (shipment.value?.id === id) shipment.value = data
  } catch {
    // The panel still shows the shipment; only the timeline is missing.
  }
}, { immediate: true })

// Status changes, `PATCH /admin/orders/{reference}`. Held locally like the
// shipment, so the badge moves at once. Only the steps a person takes are
// offered: `paid` comes from Paystack, and once there is a shipment, shipped
// and delivered are recorded on it (the API refuses them here). Cancelling is
// for unpaid orders; a paid one needs a refund, which is not built yet.
const status = ref<OrderStatus | null>(null)
watch(() => props.order?.status, (value) => { status.value = value ?? null }, { immediate: true })
const { can } = useAuth()
const toast = useToast()
const changing = ref<OrderStatus | null>(null)
const confirmingCancel = ref(false)
watch(open, () => { confirmingCancel.value = false })

const nextStep = computed<{ status: OrderStatus; label: string } | null>(() => {
  if (props.order?.kind === 'gift_card') return null
  switch (status.value) {
    case 'paid': return { status: 'processing', label: 'Start making it' }
    case 'inventory_conflict': return { status: 'processing', label: 'Resolved: start making it' }
    case 'processing': return shipment.value ? null : { status: 'shipped', label: 'Mark shipped' }
    case 'shipped': return shipment.value ? null : { status: 'delivered', label: 'Mark delivered' }
    default: return null
  }
})

// Refunds: `components/orders/RefundModal.vue`. Offered while something paid
// is left to send back, through Paystack or onto a gift card.
const REFUNDABLE: OrderStatus[] = ['paid', 'processing', 'shipped', 'delivered', 'inventory_conflict']
const refundOpen = ref(false)
const current = ref<Order | null>(null)
watch(() => props.order, (o) => { current.value = o }, { immediate: true })
const canRefund = computed(() => {
  const o = current.value
  if (!o || o.kind === 'gift_card' || !status.value || !REFUNDABLE.includes(status.value)) return false
  const cardLeft = o.giftCardRefundedAt ? 0 : o.giftCardAmount?.amount ?? 0
  return (o.refundableAmount?.amount ?? 0) > 0 || cardLeft > 0
})
function onRefunded(next: Order) {
  current.value = next
  status.value = next.status
  emit('changed')
}

async function setStatus(next: OrderStatus) {
  if (!props.order) return
  changing.value = next
  try {
    const { data } = await adminFetch<Order>(`/admin/orders/${props.order.reference}`, {
      method: 'PATCH',
      body: { status: next },
    })
    status.value = data.status
    toast.success(next === 'cancelled' ? 'Order cancelled' : 'Status updated', props.order.reference)
    confirmingCancel.value = false
    emit('changed')
  } catch (err) {
    const message = failureMessage(err)
    if (message) toast.error('Not changed', message)
  } finally {
    changing.value = null
  }
}

function onShipmentUpdated(next: Shipment) {
  shipment.value = { ...next, events: next.events ?? shipment.value?.events }
  emit('changed')
}

// The API sends `country` as the ISO code; fixtures carried a separate
// `countryCode`. Reading only `countryCode` made every real order look
// international.
const isDomestic = computed(() =>
  (props.order?.shippingAddress.countryCode ?? props.order?.shippingAddress.country) === 'GH',
)
const { formatDateTime } = useFormatters()
useBodyScrollLock(open)

const lines = computed(() => props.order?.items ?? [])

function onKeydown(e: KeyboardEvent) { if (e.key === 'Escape') open.value = false }
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity" leave-active-class="transition-opacity"
      enter-from-class="opacity-0" leave-to-class="opacity-0"
    >
      <div v-if="open && order" class="fixed inset-0 z-modal bg-ink/40" @click.self="open = false">
        <div
          class="absolute inset-y-0 right-0 flex w-full max-w-lg flex-col bg-bg-elevated shadow-overlay"
          role="dialog" aria-modal="true" :aria-label="`Order ${order.reference}`"
        >
          <header class="flex shrink-0 items-start justify-between gap-3 border-b border-border p-4 md:p-5">
            <div class="min-w-0">
              <p class="font-mono text-section text-fg-strong">{{ order.reference }}</p>
              <p class="mt-1 text-meta text-fg-muted">Placed {{ formatDateTime(order.placedAt) }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <UiStatusBadge :status="status ?? order.status" show-icon />
              <button type="button" class="toolbar-btn" aria-label="Close" @click="open = false">
                <PhX :size="18" />
              </button>
            </div>
          </header>

          <div class="min-h-0 flex-1 overflow-y-auto p-4 md:p-5">
            <div class="admin-stack">
              <section>
                <h3 class="text-meta uppercase tracking-wide text-fg-faint">Customer</h3>
                <p class="mt-1.5 text-ui text-fg-strong">{{ order.customerName }}</p>
                <p class="text-ui text-fg-muted">{{ order.customerEmail }}</p>
                <UiBadge v-if="order.isGuest" tone="outline" size="sm" class="mt-1.5">Guest checkout</UiBadge>
              </section>

              <section>
                <h3 class="text-meta uppercase tracking-wide text-fg-faint">Items</h3>
                <ul class="mt-1.5 divide-y divide-border">
                  <li v-for="i in lines" :key="i.id" class="flex items-start justify-between gap-3 py-2.5">
                    <span class="min-w-0">
                      <span class="block text-ui text-fg-strong">{{ i.productName }}</span>
                      <span class="block text-meta text-fg-faint">{{ i.variantLabel }} · {{ i.sku }}</span>
                    </span>
                    <span class="shrink-0 text-right">
                      <span class="block text-ui text-fg">{{ formatMoney(i.unitPrice) }}</span>
                      <span class="block text-meta text-fg-faint">× {{ i.quantity }}</span>
                    </span>
                  </li>
                </ul>
              </section>

              <section>
                <dl class="space-y-1.5 border-t border-border pt-3 text-ui">
                  <div class="flex justify-between"><dt class="text-fg-muted">Subtotal</dt><dd>{{ formatMoney(order.subtotal) }}</dd></div>
                  <div class="flex justify-between"><dt class="text-fg-muted">Shipping</dt><dd>{{ formatMoney(order.shippingCost) }}</dd></div>
                  <div v-if="order.giftCardAmount?.amount" class="flex justify-between">
                    <dt class="text-fg-muted">
                      <NuxtLink v-if="order.giftCardId" :to="`/gift-cards/${order.giftCardId}`" class="underline">Gift card</NuxtLink>
                      <template v-else>Gift card</template>
                    </dt>
                    <dd>−{{ formatMoney(order.giftCardAmount) }}</dd>
                  </div>
                  <div class="flex justify-between border-t border-border pt-1.5 font-medium text-fg-strong">
                    <dt>Total</dt><dd>{{ formatMoney(order.total) }}</dd>
                  </div>
                </dl>
                <p v-if="order.fxRateApplied" class="mt-2 text-meta text-fg-faint">
                  Charged in USD at a rate of {{ order.fxRateApplied.toFixed(4) }}, locked when the
                  order was placed. Later rate changes never alter this total.
                </p>
              </section>

              <section>
                <h3 class="text-meta uppercase tracking-wide text-fg-faint">Payment</h3>
                <dl class="mt-1.5 space-y-1 text-ui">
                  <div class="flex justify-between gap-3">
                    <dt class="text-fg-muted">Gateway</dt><dd class="capitalize">{{ order.paymentGateway }}</dd>
                  </div>
                  <div class="flex justify-between gap-3">
                    <dt class="text-fg-muted">Method</dt><dd>{{ PAYMENT_METHOD_LABELS[order.paymentMethod] }}</dd>
                  </div>
                  <div class="flex justify-between gap-3">
                    <dt class="text-fg-muted">Reference</dt>
                    <dd class="truncate font-mono text-meta">{{ order.paymentReference }}</dd>
                  </div>
                </dl>
              </section>

              <section>
                <h3 class="text-meta uppercase tracking-wide text-fg-faint">Delivery</h3>
                <p class="mt-1.5 flex items-center gap-2 text-ui">
                  <PhTruck :size="16" class="text-fg-faint" />
                  <span>{{ isDomestic ? 'Domestic' : 'International' }}</span>
                  <span v-if="order.shippingOption" class="text-fg-muted">
                    · {{ order.shippingOption.label }}<template v-if="order.shippingOption.source === 'fallback'">
                      (fallback rate)</template>
                  </span>
                </p>
                <address class="mt-1.5 not-italic text-ui text-fg-muted">
                  {{ order.shippingAddress.line1 }}<br>
                  {{ order.shippingAddress.city }}<template v-if="order.shippingAddress.region">, {{ order.shippingAddress.region }}</template><br>
                  {{ order.shippingAddress.country }}
                </address>
                <a
                  v-if="order.shippingAddress.lat != null && order.shippingAddress.lng != null"
                  :href="`https://www.google.com/maps/search/?api=1&query=${order.shippingAddress.lat},${order.shippingAddress.lng}`"
                  target="_blank" rel="noopener"
                  class="mt-1 inline-block text-meta text-fg-muted underline hover:text-fg-strong"
                >
                  Open delivery pin in Maps
                </a>

                <OrdersShipmentPanel
                  v-if="shipment" class="mt-3"
                  :shipment="shipment" :order-reference="order.reference"
                  @updated="onShipmentUpdated"
                />
                <p v-else-if="order.deliveryReference" class="mt-1.5 font-mono text-meta text-fg-faint">
                  {{ order.deliveryReference }}
                </p>
                <p v-else class="mt-1.5 text-meta text-fg-faint">
                  No shipment yet. One opens when the order is paid.
                </p>
              </section>
            </div>
          </div>

          <footer class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-border p-4 md:p-5">
            <UiButton variant="secondary" size="sm" to="/inbox">
              <PhChatsTeardrop :size="16" />
              WhatsApp
            </UiButton>
            <UiButton v-if="can('orders.refund') && canRefund" variant="ghost" size="sm" @click="refundOpen = true">
              Refund
            </UiButton>
            <template v-if="can('orders.update_status')">
              <template v-if="status === 'pending'">
                <UiButton v-if="!confirmingCancel" variant="ghost" size="sm" @click="confirmingCancel = true">
                  Cancel order
                </UiButton>
                <UiButton v-else variant="danger" size="sm" :loading="changing === 'cancelled'" @click="setStatus('cancelled')">
                  Cancel it and free the stock
                </UiButton>
              </template>
              <UiButton v-if="nextStep" size="sm" :loading="changing === nextStep.status" @click="setStatus(nextStep.status)">
                {{ nextStep.label }}
              </UiButton>
            </template>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
  <OrdersRefundModal v-model:open="refundOpen" :order="current" @refunded="onRefunded" />
</template>
