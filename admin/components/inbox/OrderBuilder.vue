<script setup lang="ts">
import { PhCopy, PhMinus, PhPlus, PhTrash, PhWarningCircle, PhCheckCircle } from '@phosphor-icons/vue'
import type { ChatThread, OrderBuilderData, OrderLineDraft, PlacedWhatsAppOrder, ShippingQuoteOption } from '~/types'
import { formatMoney } from '~/utils/currency'

/**
 * Build an order for a WhatsApp customer and send them the payment link.
 *
 * It goes through the same checkout as the website (`POST
 * /admin/inbox/threads/{id}/orders` → CheckoutSessionService): prices come
 * from the database, never from here; stock is held for the hours set on
 * /settings/whatsapp; delivery is priced by the same quote. The customer pays
 * by Paystack from the link in the chat, and the order then behaves exactly
 * like a web order.
 *
 * Steps: lines (from the cart the customer sent, or added from the Stock
 * tab) → contact and address → delivery price → place and send.
 */
const props = defineProps<{ thread: ChatThread | null; extraLines: OrderLineDraft[] }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ (e: 'placed', result: PlacedWhatsAppOrder): void; (e: 'consumed-extra'): void }>()

const { adminFetch } = useAdminApi()
const toast = useToast()
const { formatDateTime } = useFormatters()

const data = ref<OrderBuilderData | null>(null)
const loading = ref(false)
const lines = ref<OrderLineDraft[]>([])
const currency = ref<'GHS' | 'USD'>('GHS')
const address = reactive({ full_name: '', email: '', phone: '', line1: '', city: '', region: '', postcode: '', country: 'GH' })

const quote = ref<{ quoteId: string; options: ShippingQuoteOption[] } | null>(null)
const optionId = ref<string | null>(null)
const quoting = ref(false)
const placing = ref(false)
const error = ref<string | null>(null)
const placed = ref<PlacedWhatsAppOrder | null>(null)

async function load() {
  if (!props.thread) return
  loading.value = true
  error.value = null
  placed.value = null
  quote.value = null
  try {
    data.value = (await adminFetch<OrderBuilderData>(`/admin/inbox/threads/${props.thread.id}/order-builder`)).data
    const p = data.value.prefill
    Object.assign(address, {
      full_name: p.fullName ?? '', email: p.email ?? '', phone: p.phone ?? '',
      line1: p.line1 ?? '', city: p.city ?? '', region: p.region ?? '', postcode: p.postcode ?? '', country: p.country || 'GH',
    })
    currency.value = data.value.currency
    lines.value = (data.value.draft?.lines ?? []).map((l) => ({ ...l, quantity: Math.min(l.quantity, Math.max(l.sellable, 1)) }))
    mergeExtra()
  } catch (err) {
    error.value = message(err, 'Couldn’t open the order builder.')
  } finally {
    loading.value = false
  }
}

function mergeExtra() {
  for (const extra of props.extraLines) {
    const existing = lines.value.find((l) => l.inventoryItemId === extra.inventoryItemId)
    if (existing) existing.quantity += extra.quantity
    else lines.value.push({ ...extra })
  }
  if (props.extraLines.length) emit('consumed-extra')
}

watch(open, (isOpen) => { if (isOpen) load() })
watch(() => props.extraLines.length, (n) => { if (n && open.value && data.value) mergeExtra() })

// Anything that changes the price makes the old delivery quote stale.
watch([lines, currency, () => address.country, () => address.region, () => address.city, () => address.line1], () => {
  quote.value = null
  optionId.value = null
}, { deep: true })

function step(line: OrderLineDraft, by: number) {
  line.quantity = Math.max(1, Math.min(20, line.quantity + by))
}

function remove(line: OrderLineDraft) {
  lines.value = lines.value.filter((l) => l !== line)
}

const subtotal = computed(() => {
  const amount = lines.value.reduce((sum, l) => sum + (l.unitPrice?.amount ?? 0) * l.quantity, 0)
  return { amount, currency: 'GHS' as const }
})

const lineProblems = computed(() => lines.value.some((l) => l.problem || l.quantity > l.sellable))

const contactReady = computed(() =>
  address.full_name.trim() && /\S+@\S+\.\S+/.test(address.email) && address.phone.trim()
  && address.line1.trim() && address.city.trim() && /^[A-Za-z]{2}$/.test(address.country),
)

const items = computed(() => lines.value.map((l) => ({ inventory_item_id: l.inventoryItemId, quantity: l.quantity })))

function body() {
  return {
    items: items.value,
    currency: currency.value,
    shipping_address: { ...address, country: address.country.toUpperCase(), region: address.region || null, postcode: address.postcode || null },
  }
}

async function getQuote() {
  quoting.value = true
  error.value = null
  try {
    const res = await adminFetch<{ quoteId: string; options: ShippingQuoteOption[] }>('/checkout/shipping-quote', { method: 'POST', body: body() })
    quote.value = res.data
    optionId.value = res.data.options[0]?.id ?? null
  } catch (err) {
    error.value = message(err, 'Delivery couldn’t be priced for this address.')
  } finally {
    quoting.value = false
  }
}

const chosen = computed(() => quote.value?.options.find((o) => o.id === optionId.value) ?? null)

async function place() {
  if (!props.thread || !quote.value || !optionId.value) return
  placing.value = true
  error.value = null
  try {
    const res = await adminFetch<PlacedWhatsAppOrder>(`/admin/inbox/threads/${props.thread.id}/orders`, {
      method: 'POST',
      body: {
        ...body(),
        shipping_quote_id: quote.value.quoteId,
        shipping_option_id: optionId.value,
        draft_reference: data.value?.draft?.reference ?? null,
      },
    })
    placed.value = res.data
    emit('placed', res.data)
    if (!res.data.warning) toast.success(`Order ${res.data.order.reference} placed`, 'The payment link is in the chat.')
  } catch (err) {
    error.value = message(err, 'The order wasn’t placed. Nothing was held.')
  } finally {
    placing.value = false
  }
}

async function copyLink(url: string | null) {
  if (!url) return
  await navigator.clipboard?.writeText(url)
  toast.success('Payment link copied')
}

function message(err: unknown, fallback: string): string {
  const e = err as { statusCode?: number; data?: { message?: string; errors?: Record<string, string[]> } }
  return Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? fallback
}

const canPlace = computed(() => lines.value.length && !lineProblems.value && contactReady.value && chosen.value && !placing.value)
</script>

<template>
  <UiModal
    v-model:open="open" size="lg" title="New WhatsApp order"
    :description="thread ? `For ${thread.contactName}. Paid by Paystack link in the chat.` : undefined"
  >
    <p v-if="loading" class="py-8 text-center text-meta text-fg-faint">Loading…</p>

    <!-- Placed -->
    <div v-else-if="placed" class="flex flex-col gap-3">
      <div class="flex items-start gap-3 rounded-lg border border-success/30 bg-success-soft px-4 py-3">
        <PhCheckCircle :size="20" class="mt-px shrink-0 text-success" />
        <div class="min-w-0 text-meta">
          <p class="text-ui font-medium text-fg-strong">Order {{ placed.order.reference }} is waiting for payment</p>
          <p class="mt-1 text-fg-muted">
            {{ formatMoney(placed.order.total) }}. Stock is held until {{ formatDateTime(placed.order.heldUntil) }}.
            Once paid, it moves to Orders like any other.
          </p>
        </div>
      </div>
      <p v-if="placed.warning" class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft px-3.5 py-3 text-meta text-warning">
        <PhWarningCircle :size="16" class="mt-px shrink-0" />
        {{ placed.warning }}
      </p>
      <div v-if="placed.paymentUrl" class="flex items-center gap-2 rounded-lg bg-bg-sunken px-3 py-2">
        <span class="min-w-0 flex-1 truncate font-mono text-meta text-fg-muted">{{ placed.paymentUrl }}</span>
        <UiButton variant="secondary" size="sm" @click="copyLink(placed.paymentUrl)"><PhCopy :size="15" /> Copy</UiButton>
      </div>
    </div>

    <div v-else-if="data" class="flex flex-col gap-5">
      <p v-if="data.draft" class="rounded-lg bg-bg-sunken px-3 py-2 text-meta text-fg-muted">
        Started from the cart the customer sent from the website ({{ data.draft.reference }}). Prices and stock are today’s.
      </p>

      <!-- Lines -->
      <section>
        <h3 class="mb-2 text-micro uppercase tracking-wide text-fg-faint">Items</h3>
        <p v-if="!lines.length" class="text-meta text-fg-faint">
          No items yet. Use “Add to order” on the Stock tab beside the chat.
        </p>
        <ul v-else class="flex flex-col divide-y divide-border rounded-lg border border-border">
          <li v-for="l in lines" :key="l.inventoryItemId" class="flex items-center gap-3 px-3 py-2">
            <div class="min-w-0 flex-1">
              <p class="truncate text-ui text-fg-strong">{{ l.productName }} <span class="text-fg-faint">{{ l.variantLabel }}</span></p>
              <p class="text-meta" :class="l.problem || l.quantity > l.sellable ? 'text-danger' : 'text-fg-faint'">
                {{ l.unitPrice ? formatMoney(l.unitPrice) : 'No price' }} ·
                {{ l.problem ?? (l.quantity > l.sellable ? `Only ${l.sellable} available` : `${l.sellable} available`) }}
              </p>
            </div>
            <div class="flex items-center gap-1">
              <button type="button" class="toolbar-btn !size-8" aria-label="One fewer" @click="step(l, -1)"><PhMinus :size="14" /></button>
              <span class="w-6 text-center text-ui tabular-nums">{{ l.quantity }}</span>
              <button type="button" class="toolbar-btn !size-8" aria-label="One more" @click="step(l, 1)"><PhPlus :size="14" /></button>
              <button type="button" class="toolbar-btn !size-8" aria-label="Remove" @click="remove(l)"><PhTrash :size="14" /></button>
            </div>
          </li>
        </ul>
        <div class="mt-2 flex items-center justify-between gap-3">
          <label class="flex items-center gap-2 text-meta text-fg-muted">
            Charge in
            <select v-model="currency" class="field !min-h-0 !py-1.5">
              <option value="GHS">GHS</option>
              <option value="USD">USD (rate locked when placed)</option>
            </select>
          </label>
          <p class="text-meta text-fg-muted">Items {{ formatMoney(subtotal) }}</p>
        </div>
      </section>

      <!-- Contact and address -->
      <section>
        <h3 class="mb-2 text-micro uppercase tracking-wide text-fg-faint">
          Customer and delivery
          <span v-if="data.prefill.fromLastOrder" class="normal-case text-fg-faint">· filled from {{ data.prefill.fromLastOrder }}</span>
        </h3>
        <div class="grid gap-3 sm:grid-cols-2">
          <UiField v-model="address.full_name" label="Full name" />
          <UiField v-model="address.phone" label="Phone" />
          <UiField v-model="address.email" label="Email" type="email" hint="Paystack needs one, and the receipt goes there." class="sm:col-span-2" />
          <UiField v-model="address.line1" label="Address" class="sm:col-span-2" />
          <UiField v-model="address.city" label="Town or city" />
          <div>
            <label class="field-label" for="builder-region">Region</label>
            <input id="builder-region" v-model="address.region" list="builder-regions" class="field w-full" :disabled="address.country.toUpperCase() !== 'GH'">
            <datalist id="builder-regions"><option v-for="r in data.regions" :key="r" :value="r" /></datalist>
          </div>
          <UiField v-model="address.country" label="Country code" hint="GH for Ghana; GB, US, NG…" />
          <UiField v-model="address.postcode" label="Postcode (optional)" />
        </div>
      </section>

      <!-- Delivery -->
      <section>
        <div class="flex items-center justify-between gap-3">
          <h3 class="text-micro uppercase tracking-wide text-fg-faint">Delivery</h3>
          <UiButton variant="secondary" size="sm" :loading="quoting" :disabled="!lines.length || !contactReady" @click="getQuote">
            {{ quote ? 'Price again' : 'Get delivery price' }}
          </UiButton>
        </div>
        <ul v-if="quote" class="mt-2 flex flex-col gap-1.5">
          <li v-for="o in quote.options" :key="o.id">
            <label class="flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2" :class="optionId === o.id ? 'border-accent' : 'border-border'">
              <input v-model="optionId" type="radio" :value="o.id" class="accent-[rgb(var(--accent))]">
              <span class="min-w-0 flex-1 text-meta">
                <span class="text-fg-strong">{{ o.label }}</span>
                <span v-if="o.eta" class="text-fg-faint"> · {{ o.eta }}</span>
              </span>
              <span class="text-meta text-fg-muted">{{ o.free ? 'Free' : formatMoney({ amount: o.amountGhs, currency: 'GHS' }) }}</span>
            </label>
          </li>
        </ul>
      </section>

      <p v-if="!data.windowOpen" class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft px-3.5 py-3 text-meta text-warning">
        <PhWarningCircle :size="16" class="mt-px shrink-0" />
        The 24-hour reply window has closed.
        {{ data.paymentTemplateReady
          ? 'The link will go out with the approved “payment_link” template.'
          : 'There is no approved “payment_link” template, so the link won’t be sent by itself; you’ll get it to pass on.' }}
      </p>

      <p class="text-meta text-fg-faint">
        Placing the order holds the stock for {{ data.holdHours }} hours while the customer pays. The total is
        worked out on the server from today’s prices<template v-if="currency === 'USD'"> and dollar rate</template>.
      </p>

      <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ error }}</p>
    </div>

    <p v-else-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ error }}</p>

    <template #footer>
      <UiButton variant="secondary" size="sm" @click="open = false">{{ placed ? 'Done' : 'Cancel' }}</UiButton>
      <UiButton v-if="!placed && data" size="sm" :loading="placing" :disabled="!canPlace" @click="place">
        Place order and send link
      </UiButton>
    </template>
  </UiModal>
</template>
