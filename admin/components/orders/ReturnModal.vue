<script setup lang="ts">
import type { ReturnRequest, ReturnStatus } from '~/types'
import { formatMoney } from '~/utils/currency'
import { humanise } from '~/utils/formatters'

/**
 * Move a return on, `PATCH /admin/returns/{id}`.
 *
 * Requested → approved or rejected; approved → received (the pair is back) or
 * rejected; received → refunded, exchanged or rejected. Refunded, exchanged
 * and rejected close it, and the API won't reopen one.
 *
 * "Refunded" records the decision and marks the order refunded. It does not
 * move money: that is still done in the Paystack dashboard, which the modal
 * says before anyone picks it.
 */
const props = defineProps<{ request: ReturnRequest | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [] }>()

const { adminFetch } = useAdminApi()
const toast = useToast()
const { formatDate } = useFormatters()

const NEXT: Partial<Record<ReturnStatus, ReturnStatus[]>> = {
  requested: ['approved', 'rejected'],
  approved: ['received', 'rejected'],
  received: ['refunded', 'exchanged', 'rejected'],
}

const status = ref<ReturnStatus | ''>('')
const notes = ref('')
const saving = ref(false)
const error = ref<string | null>(null)

const options = computed(() =>
  (props.request ? NEXT[props.request.status] ?? [] : []).map((s) => ({ value: s, label: humanise(s) })),
)

watch(() => [open.value, props.request] as const, ([isOpen, r]) => {
  if (!isOpen || !r) return
  status.value = options.value[0]?.value as ReturnStatus ?? ''
  notes.value = r.notes ?? ''
  error.value = null
}, { immediate: true })

async function save() {
  if (!props.request || !status.value) return
  saving.value = true
  error.value = null
  try {
    await adminFetch(`/admin/returns/${props.request.id}`, {
      method: 'PATCH',
      body: { status: status.value, notes: notes.value.trim() || null },
    })
    toast.success(`Return ${humanise(status.value).toLowerCase()}`, props.request.orderReference)
    emit('saved')
    open.value = false
  } catch (err) {
    error.value = failureMessage(err)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UiModal
    v-model:open="open" title="Update return"
    :description="request ? `${request.orderReference} · ${request.customerName}` : undefined"
  >
    <div v-if="request" class="flex flex-col gap-4">
      <dl class="space-y-2 text-ui">
        <div class="flex justify-between gap-3">
          <dt class="text-fg-muted">Items</dt>
          <dd class="text-right text-fg-strong">{{ request.itemsSummary }}</dd>
        </div>
        <div class="flex justify-between gap-3">
          <dt class="text-fg-muted">Reason</dt>
          <dd class="text-fg-strong">{{ humanise(request.reason) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
          <dt class="text-fg-muted">Window</dt>
          <dd class="text-fg-strong">Closes {{ formatDate(request.windowClosesAt) }}</dd>
        </div>
        <div v-if="request.refundAmount" class="flex justify-between gap-3">
          <dt class="text-fg-muted">Refund due</dt>
          <dd class="text-fg-strong">{{ formatMoney(request.refundAmount) }}</dd>
        </div>
      </dl>

      <p v-if="!request.isEligible" class="rounded-lg bg-warning-soft px-3 py-2.5 text-meta text-warning">
        Flagged as not returnable{{ request.ineligibleReason ? `: ${request.ineligibleReason}` : '' }}.
      </p>

      <UiSelect v-model="status" label="Move it to" :options="options" />
      <p v-if="status === 'refunded'" class="-mt-2 rounded-lg bg-bg-sunken px-3 py-2.5 text-meta text-fg-muted">
        This records the refund and marks the order refunded. It doesn’t send money: make the
        refund in the Paystack dashboard as well, to the original payment method.
      </p>

      <UiField v-model="notes" label="Notes" type="textarea" :rows="3" hint="For the team. Not sent to the customer." />

      <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ error }}</p>
    </div>

    <template #footer>
      <UiButton variant="secondary" size="sm" @click="open = false">Cancel</UiButton>
      <UiButton size="sm" :loading="saving" :disabled="!status" @click="save">Save</UiButton>
    </template>
  </UiModal>
</template>
