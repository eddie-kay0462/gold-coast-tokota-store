<script setup lang="ts">
import type { Shipment } from '~/types'

/**
 * Record a courier staff booked themselves:
 * `POST /admin/shipments/{id}/manual-dispatch`.
 *
 * This is the GHANA zone's whole dispatch step, and every other zone's while
 * its courier API is switched off. Saving marks the parcel in transit, moves
 * the order to shipped and sends the customer their dispatch message, once.
 * A failed automated booking can be rescued the same way; the API keeps the
 * failed one on record and opens a manual one in its place.
 */
const props = defineProps<{ shipment: Shipment | null, orderReference: string }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [shipment: Shipment] }>()

const { adminFetch } = useAdminApi()
const toast = useToast()

const form = reactive({
  courierName: '',
  trackingNumber: '',
  trackingUrl: '',
  /** Cedis as typed; sent as pesewas. */
  costPaid: '' as string | number,
  notes: '',
})
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, (isOpen) => {
  if (!isOpen) return
  Object.assign(form, { courierName: '', trackingNumber: '', trackingUrl: '', costPaid: '', notes: '' })
  error.value = null
})

const costMinor = computed(() => {
  if (form.costPaid === '' || form.costPaid === null) return null
  const n = Number(form.costPaid)
  return Number.isFinite(n) && n >= 0 ? Math.round(n * 100) : NaN
})
const valid = computed(() => form.courierName.trim() !== '' && !Number.isNaN(costMinor.value))

async function save() {
  if (!props.shipment || !valid.value) return
  saving.value = true
  error.value = null
  try {
    const { data } = await adminFetch<Shipment>(`/admin/shipments/${props.shipment.id}/manual-dispatch`, {
      method: 'POST',
      body: {
        courier_name: form.courierName.trim(),
        tracking_number: form.trackingNumber.trim() || null,
        tracking_url: form.trackingUrl.trim() || null,
        cost_paid_ghs: costMinor.value,
        notes: form.notes.trim() || null,
      },
    })
    emit('saved', data)
    toast.success('Dispatch recorded', `${props.orderReference} is on its way with ${data.courierName}. The customer has been told.`)
    open.value = false
  } catch (err: unknown) {
    const e = err as { statusCode?: number; data?: { errors?: Record<string, string[]>; message?: string } }
    if (e.statusCode === 401 || e.statusCode === 419) return // useAdminApi is redirecting to /login
    error.value = Object.values(e.data?.errors ?? {})[0]?.[0]
      ?? e.data?.message
      ?? (e.statusCode ? 'Saving failed. Try again in a moment.' : 'Couldn’t reach the server, so nothing was saved.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UiModal
    v-model:open="open" size="sm"
    title="Record dispatch"
    :description="`${orderReference} · booked by hand`"
  >
    <form v-if="shipment" class="flex flex-col gap-4" @submit.prevent="save">
      <div>
        <label class="field-label" for="dispatch-courier">Courier</label>
        <input
          id="dispatch-courier" v-model="form.courierName" type="text" maxlength="120"
          class="field w-full" placeholder="e.g. VIP Bus Parcels" required
        >
      </div>

      <div>
        <label class="field-label" for="dispatch-tracking">Tracking number</label>
        <input id="dispatch-tracking" v-model="form.trackingNumber" type="text" maxlength="128" class="field w-full">
      </div>

      <div>
        <label class="field-label" for="dispatch-url">Tracking link</label>
        <input
          id="dispatch-url" v-model="form.trackingUrl" type="url" maxlength="2048"
          class="field w-full" placeholder="https://"
        >
        <p class="mt-1.5 text-meta text-fg-faint">Sent to the customer if the courier gave one.</p>
      </div>

      <div>
        <label class="field-label" for="dispatch-cost">What we paid (GH₵)</label>
        <input
          id="dispatch-cost" v-model="form.costPaid" type="number" min="0" step="0.01"
          class="field w-full" inputmode="decimal"
        >
      </div>

      <div>
        <label class="field-label" for="dispatch-notes">Notes</label>
        <textarea id="dispatch-notes" v-model="form.notes" rows="3" maxlength="2000" class="field w-full" />
      </div>

      <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">
        {{ error }}
      </p>
    </form>

    <template #footer>
      <UiButton variant="secondary" size="sm" @click="open = false">Cancel</UiButton>
      <UiButton size="sm" :loading="saving" :disabled="!valid" @click="save">Mark dispatched</UiButton>
    </template>
  </UiModal>
</template>
