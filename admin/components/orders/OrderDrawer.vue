<script setup lang="ts">
import { PhChatsTeardrop, PhTruck, PhX } from '@phosphor-icons/vue'
import type { Order } from '~/types'
import { PAYMENT_METHOD_LABELS } from '~/types'
import { formatMoney } from '~/utils/currency'

/**
 * Order detail drawer — README Feature 9 specifies a drawer rather than a
 * page, so working through a queue keeps your place in the list.
 */
const props = defineProps<{ order: Order | null }>()
const open = defineModel<boolean>('open', { default: false })
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
              <UiStatusBadge :status="order.status" show-icon />
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
                  <span class="uppercase">{{ order.deliveryProvider }}</span>
                  <span class="text-fg-muted">
                    · {{ order.shippingAddress.countryCode === 'GH' ? 'Domestic' : 'International' }}
                  </span>
                </p>
                <address class="mt-1.5 not-italic text-ui text-fg-muted">
                  {{ order.shippingAddress.line1 }}<br>
                  {{ order.shippingAddress.city }}, {{ order.shippingAddress.region }}<br>
                  {{ order.shippingAddress.country }}
                </address>
                <p v-if="order.deliveryReference" class="mt-1.5 font-mono text-meta text-fg-faint">
                  {{ order.deliveryReference }}
                </p>
              </section>
            </div>
          </div>

          <footer class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-border p-4 md:p-5">
            <UiButton variant="secondary" size="sm" to="/inbox">
              <PhChatsTeardrop :size="16" />
              WhatsApp
            </UiButton>
            <UiPermissionGate capability="orders.refund" quiet>
              <UiButton variant="ghost" size="sm">Refund</UiButton>
            </UiPermissionGate>
            <UiPermissionGate capability="orders.update_status" quiet>
              <UiButton size="sm">Advance status</UiButton>
            </UiPermissionGate>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
