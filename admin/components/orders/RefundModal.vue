<script setup lang="ts">
import type { Order } from '~/types'
import { formatMoney } from '~/utils/currency'

/**
 * Refund an order, `POST /admin/orders/{reference}/refund`: back through
 * Paystack to whatever paid, and any gift card part back on its card.
 *
 * The amount starts at what's left to refund less delivery, because §9 keeps
 * delivery charges unless the mistake was ours. Whoever sends it can change
 * it, up to what's left.
 */
const props = defineProps<{ order: Order | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ refunded: [order: Order] }>()

const { adminFetch } = useAdminApi()
const toast = useToast()

const amount = ref('')
const returnCard = ref(true)
const note = ref('')
const saving = ref(false)
const error = ref<string | null>(null)

const currency = computed(() => props.order?.total.currency ?? 'GHS')
const left = computed(() => props.order?.refundableAmount?.amount ?? 0)
const cardPart = computed(() =>
  props.order && !props.order.giftCardRefundedAt ? props.order.giftCardAmount?.amount ?? 0 : 0,
)
const suggested = computed(() => Math.max(0, left.value - (props.order?.shippingCost.amount ?? 0)))

watch(() => [open.value, props.order] as const, ([isOpen]) => {
  if (!isOpen) return
  amount.value = (suggested.value / 100).toFixed(2)
  returnCard.value = true
  note.value = ''
  error.value = null
}, { immediate: true })

const minor = computed(() => Math.round(Number(amount.value || 0) * 100))
const valid = computed(() =>
  Number.isFinite(minor.value) && minor.value >= 0 && minor.value <= left.value
  && (minor.value > 0 || (returnCard.value && cardPart.value > 0)),
)

async function send() {
  if (!props.order || !valid.value) return
  saving.value = true
  error.value = null
  try {
    const { data } = await adminFetch<Order>(`/admin/orders/${props.order.reference}/refund`, {
      method: 'POST',
      body: { amount: minor.value, return_gift_card: returnCard.value, note: note.value.trim() || null },
    })
    toast.success('Refund sent', `${formatMoney({ amount: minor.value, currency: currency.value })} to the original payment method. It reaches them in 7 to 14 business days.`)
    emit('refunded', data)
    open.value = false
  } catch (err) {
    error.value = failureMessage(err)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UiModal v-model:open="open" title="Refund order" :description="order?.reference">
    <div v-if="order" class="flex flex-col gap-4">
      <dl class="space-y-2 text-ui">
        <div class="flex justify-between gap-3">
          <dt class="text-fg-muted">Paid</dt>
          <dd class="text-fg-strong">{{ formatMoney(order.total) }}</dd>
        </div>
        <div v-if="order.refundedAmount?.amount" class="flex justify-between gap-3">
          <dt class="text-fg-muted">Already refunded</dt>
          <dd class="text-fg-strong">{{ formatMoney(order.refundedAmount) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
          <dt class="text-fg-muted">Left to refund through Paystack</dt>
          <dd class="font-medium text-fg-strong">{{ formatMoney({ amount: left, currency }) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
          <dt class="text-fg-muted">Of which delivery</dt>
          <dd class="text-fg-strong">{{ formatMoney(order.shippingCost) }}</dd>
        </div>
      </dl>

      <div>
        <UiField v-model="amount" :label="`Refund (${currency})`" type="number" />
        <p class="mt-1 text-meta text-fg-faint">
          Suggested: everything left less delivery, which isn’t refunded unless the mistake was ours.
        </p>
      </div>

      <label v-if="cardPart > 0" class="flex items-start gap-2.5 text-ui text-fg">
        <input v-model="returnCard" type="checkbox" class="mt-0.5 size-4 rounded-sm">
        <span>
          Put the {{ formatMoney({ amount: cardPart, currency }) }} a gift card paid back on the card
        </span>
      </label>

      <UiField v-model="note" label="Note" hint="Optional. Kept with the refund in Paystack." />

      <p class="rounded-lg bg-bg-sunken px-3 py-2.5 text-meta text-fg-muted">
        This sends money now. Paystack pays it out to the card or mobile money account that paid,
        usually within 7 to 14 business days. Stock isn’t changed: book a returned pair back in
        through Inventory.
      </p>

      <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ error }}</p>
    </div>

    <template #footer>
      <UiButton variant="secondary" size="sm" @click="open = false">Cancel</UiButton>
      <UiButton variant="danger" size="sm" :loading="saving" :disabled="!valid" @click="send">
        Send refund
      </UiButton>
    </template>
  </UiModal>
</template>
