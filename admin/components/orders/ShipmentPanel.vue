<script setup lang="ts">
import { PhArrowSquareOut, PhWarningCircle } from '@phosphor-icons/vue'
import type { Shipment } from '~/types'
import { formatMoney } from '~/utils/currency'

/**
 * An order's delivery booking: who has it, where it is, what it cost, and the
 * history behind that. Every action here posts to the shipment, and the order
 * follows it — dispatched makes the order shipped, delivered makes it
 * delivered — so the two can never disagree.
 */
const props = defineProps<{ shipment: Shipment, orderReference: string }>()
const emit = defineEmits<{ updated: [shipment: Shipment] }>()

const { adminFetch } = useAdminApi()
const toast = useToast()
const { formatDateTime } = useFormatters()

const CARRIERS: Record<string, string> = {
  manual: 'By hand', yango: 'Yango', swoove: 'Swoove', dhl: 'DHL Express',
}
const ZONES: Record<string, string> = { accra: 'Accra zone', ghana: 'Ghana zone', international: 'International' }

const closed = computed(() => ['delivered', 'failed', 'cancelled', 'returned'].includes(props.shipment.status))
const canDispatchByHand = computed(() =>
  (props.shipment.carrier === 'manual' && props.shipment.status === 'pending') || props.shipment.status === 'failed',
)
// A courier with an API is moved along by its own status updates. Marking one
// by hand is only offered once a courier has the parcel (to record a lost or
// undelivered parcel), never mid-booking, where it would leave a live booking
// that could still send a courier.
const canMarkByHand = computed(() => !closed.value && (
  props.shipment.carrier === 'manual'
  || ['awaiting_pickup', 'in_transit', 'out_for_delivery'].includes(props.shipment.status)
))

const dispatchOpen = ref(false)
const failOpen = ref(false)
const failReason = ref('')
const busy = ref<'delivered' | 'failed' | 'packed' | null>(null)

/**
 * Packing comes first for every shipment. For a courier with an API it is
 * what books the courier, which is why it is a separate, deliberate step and
 * not something payment does: a Yango courier arrives within minutes.
 */
const canPack = computed(() => props.shipment.status === 'pending' && !props.shipment.packedAt)
const books = computed(() => props.shipment.carrier !== 'manual')

async function pack() {
  busy.value = 'packed'
  try {
    const { data } = await adminFetch<Shipment>(`/admin/shipments/${props.shipment.id}/pack`, { method: 'POST' })
    emit('updated', data)
    toast.success(
      'Marked packed',
      books.value
        ? `Booking a ${CARRIERS[props.shipment.carrier]} courier for ${props.orderReference}. This page updates as it goes.`
        : `${props.orderReference} is ready. Record the dispatch once a courier is booked.`,
    )
  } catch (err: unknown) {
    const e = err as { statusCode?: number; data?: { message?: string } }
    if (e.statusCode === 401 || e.statusCode === 419) return
    toast.error('Not packed', e.data?.message ?? 'That didn’t save. Try again in a moment.')
  } finally {
    busy.value = null
  }
}
const failError = ref<string | null>(null)

async function mark(action: 'delivered' | 'failed') {
  busy.value = action
  failError.value = null
  try {
    const { data } = await adminFetch<Shipment>(`/admin/shipments/${props.shipment.id}/mark-${action}`, {
      method: 'POST',
      body: action === 'failed' ? { reason: failReason.value.trim() } : {},
    })
    emit('updated', data)
    failOpen.value = false
    toast.success(
      action === 'delivered' ? 'Marked delivered' : 'Marked failed',
      action === 'delivered'
        ? `${props.orderReference} is delivered. The returns window starts today.`
        : `${props.orderReference} is flagged for attention.`,
    )
  } catch (err: unknown) {
    const e = err as { statusCode?: number; data?: { errors?: Record<string, string[]>; message?: string } }
    if (e.statusCode === 401 || e.statusCode === 419) return
    const message = Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? 'That didn’t save. Try again in a moment.'
    if (action === 'failed') failError.value = message
    else toast.error('Not saved', message)
  } finally {
    busy.value = null
  }
}

/** Labels and invoices sit in private storage; the API hands out a short-lived link. */
async function openDocument(kind: 'label' | 'invoice') {
  try {
    const { data } = await adminFetch<{ url: string }>(`/admin/shipments/${props.shipment.id}/documents/${kind}`)
    window.open(data.url, '_blank', 'noopener')
  } catch (err: unknown) {
    const e = err as { statusCode?: number }
    if (e.statusCode === 401 || e.statusCode === 419) return
    toast.error('Couldn’t open the document', 'Try again in a moment.')
  }
}

watch(failOpen, (isOpen) => {
  if (isOpen) { failReason.value = ''; failError.value = null }
})
</script>

<template>
  <div class="rounded-lg border border-border p-3.5">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex flex-wrap items-center gap-2">
        <UiBadge :tone="shipment.carrier === 'manual' ? 'neutral' : 'info'">
          {{ CARRIERS[shipment.carrier] ?? shipment.carrier }}
        </UiBadge>
        <span class="text-meta text-fg-muted">{{ ZONES[shipment.zone] ?? shipment.zone }}</span>
        <span v-if="shipment.attempt > 1" class="text-meta text-fg-faint">· attempt {{ shipment.attempt }}</span>
      </div>
      <UiStatusBadge :status="shipment.status" show-icon />
    </div>

    <p
      v-if="shipment.failureReason"
      class="mt-3 flex items-start gap-2 rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger"
    >
      <PhWarningCircle :size="16" class="mt-px shrink-0" />
      {{ shipment.failureReason }}
    </p>

    <dl class="mt-3 space-y-1 text-ui">
      <div v-if="shipment.courierName" class="flex justify-between gap-3">
        <dt class="text-fg-muted">Courier</dt><dd>{{ shipment.courierName }}</dd>
      </div>
      <div v-if="shipment.carrierReference" class="flex justify-between gap-3">
        <dt class="text-fg-muted">Tracking</dt>
        <dd class="flex min-w-0 items-center gap-1.5">
          <span class="truncate font-mono text-meta">{{ shipment.carrierReference }}</span>
          <a
            v-if="shipment.trackingUrl" :href="shipment.trackingUrl" target="_blank" rel="noopener"
            class="shrink-0 text-fg-muted hover:text-fg-strong" aria-label="Open tracking page"
          >
            <PhArrowSquareOut :size="14" />
          </a>
        </dd>
      </div>
      <div v-else-if="shipment.trackingUrl" class="flex justify-between gap-3">
        <dt class="text-fg-muted">Tracking</dt>
        <dd><a :href="shipment.trackingUrl" target="_blank" rel="noopener" class="underline">Open</a></dd>
      </div>
      <div v-if="shipment.quotedCost" class="flex justify-between gap-3">
        <dt class="text-fg-muted">Courier quoted</dt><dd>{{ formatMoney(shipment.quotedCost) }}</dd>
      </div>
      <div v-if="shipment.actualCost" class="flex justify-between gap-3">
        <dt class="text-fg-muted">We paid</dt><dd>{{ formatMoney(shipment.actualCost) }}</dd>
      </div>
      <div v-if="shipment.pickupReference" class="flex justify-between gap-3">
        <dt class="text-fg-muted">Pickup booked</dt>
        <dd class="truncate font-mono text-meta">{{ shipment.pickupReference }}</dd>
      </div>
      <div v-if="shipment.packedAt" class="flex justify-between gap-3">
        <dt class="text-fg-muted">Packed</dt><dd>{{ formatDateTime(shipment.packedAt) }}</dd>
      </div>
      <div v-if="shipment.dispatchedAt" class="flex justify-between gap-3">
        <dt class="text-fg-muted">Dispatched</dt><dd>{{ formatDateTime(shipment.dispatchedAt) }}</dd>
      </div>
      <div v-if="shipment.deliveredAt" class="flex justify-between gap-3">
        <dt class="text-fg-muted">Delivered</dt><dd>{{ formatDateTime(shipment.deliveredAt) }}</dd>
      </div>
    </dl>

    <p v-if="shipment.notes" class="mt-2 whitespace-pre-line text-meta text-fg-muted">{{ shipment.notes }}</p>

    <div v-if="shipment.hasLabel || shipment.hasInvoice" class="mt-3 flex flex-wrap gap-2">
      <UiButton v-if="shipment.hasLabel" variant="secondary" size="sm" @click="openDocument('label')">Print label</UiButton>
      <UiButton v-if="shipment.hasInvoice" variant="secondary" size="sm" @click="openDocument('invoice')">Customs invoice</UiButton>
    </div>

    <UiPermissionGate capability="shipments.update" quiet>
      <div v-if="!closed || canDispatchByHand" class="mt-3 flex flex-wrap gap-2 border-t border-border pt-3">
        <UiButton v-if="canPack" size="sm" :loading="busy === 'packed'" @click="pack">
          {{ books ? `Mark packed & book ${CARRIERS[shipment.carrier]}` : 'Mark packed' }}
        </UiButton>
        <UiButton v-if="canDispatchByHand && !(canPack && books)" :variant="canPack ? 'secondary' : 'primary'" size="sm" @click="dispatchOpen = true">
          {{ shipment.status === 'failed' ? 'Dispatch by hand' : 'Record dispatch' }}
        </UiButton>
        <UiButton
          v-if="canMarkByHand" variant="secondary" size="sm"
          :loading="busy === 'delivered'" @click="mark('delivered')"
        >
          Mark delivered
        </UiButton>
        <UiButton v-if="canMarkByHand" variant="ghost" size="sm" @click="failOpen = true">Mark failed</UiButton>
      </div>
    </UiPermissionGate>

    <ol v-if="shipment.events?.length" class="mt-3 space-y-2 border-t border-border pt-3">
      <li v-for="event in shipment.events" :key="event.id" class="text-meta">
        <span class="text-fg-faint">{{ formatDateTime(event.occurredAt) }}</span>
        <span class="block text-fg">{{ event.message ?? (event.status ? `Now ${event.status.replace(/_/g, ' ')}` : 'Updated') }}</span>
      </li>
    </ol>

    <OrdersManualDispatchModal
      v-model:open="dispatchOpen" :shipment="shipment" :order-reference="orderReference"
      @saved="emit('updated', $event)"
    />

    <UiModal v-model:open="failOpen" size="sm" title="Mark delivery failed" :description="orderReference">
      <form class="flex flex-col gap-4" @submit.prevent="mark('failed')">
        <div>
          <label class="field-label" for="fail-reason">What happened</label>
          <input
            id="fail-reason" v-model="failReason" type="text" maxlength="255" class="field w-full"
            placeholder="e.g. Parcel lost at the station" required
          >
          <p class="mt-1.5 text-meta text-fg-faint">The team is alerted, and the order shows under Needs attention.</p>
        </div>
        <p v-if="failError" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">
          {{ failError }}
        </p>
      </form>
      <template #footer>
        <UiButton variant="secondary" size="sm" @click="failOpen = false">Cancel</UiButton>
        <UiButton size="sm" :loading="busy === 'failed'" :disabled="!failReason.trim()" @click="mark('failed')">
          Mark failed
        </UiButton>
      </template>
    </UiModal>
  </div>
</template>
