<script setup lang="ts">
import type { InventoryItem, InventoryMovement } from '~/types'

/**
 * Set a variant's stock count, `PATCH /admin/inventory/{id}`.
 *
 * An absolute count, not a delta: whoever is restocking has just counted the
 * shelf. Reserved units are shown but never editable; they belong to
 * checkouts in progress, and the API refuses a count below them (the 422
 * message is shown as-is, since it names the number held).
 *
 * History lists every movement on the variant, newest first: holds from web
 * checkouts and WhatsApp orders, releases, expiries, sales and recounts, with
 * who made each recount and why.
 */
const props = defineProps<{ item: InventoryItem | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [item: InventoryItem] }>()

const { adminFetch } = useAdminApi()
const toast = useToast()
const { formatRelative } = useFormatters()

const view = ref<'count' | 'history'>('count')
const quantity = ref(0)
const threshold = ref(0)
const note = ref('')
const saving = ref(false)
const movements = ref<InventoryMovement[]>([])
const historyLoading = ref(false)
const historyError = ref(false)
const error = ref<string | null>(null)

watch(() => [props.item, open.value] as const, ([item, isOpen]) => {
  if (!item || !isOpen) return
  quantity.value = item.quantityAvailable
  threshold.value = item.lowStockThreshold
  note.value = ''
  error.value = null
  view.value = 'count'
  movements.value = []
}, { immediate: true })

async function loadHistory() {
  if (!props.item) return
  historyLoading.value = true
  historyError.value = false
  try {
    const { data } = await adminFetch<InventoryMovement[]>(`/admin/inventory/${props.item.id}/movements?per_page=50`)
    movements.value = data
  } catch {
    historyError.value = true
  } finally {
    historyLoading.value = false
  }
}

watch(view, (v) => { if (v === 'history') loadHistory() })

const reasonLabel: Record<InventoryMovement['reason'], string> = {
  hold: 'Held for an order',
  hold_released: 'Hold released',
  hold_expired: 'Hold expired',
  sale: 'Sold',
  adjustment: 'Recounted',
}

function signed(n: number): string {
  return n > 0 ? `+${n}` : String(n)
}

const variant = computed(() =>
  props.item
    ? Object.entries(props.item.variantAttributes).map(([k, v]) => `${k} ${v}`).join(' · ')
    : '',
)

const valid = computed(() =>
  Number.isInteger(quantity.value) && quantity.value >= 0
  && Number.isInteger(threshold.value) && threshold.value >= 0,
)

async function save() {
  if (!props.item || !valid.value) return
  saving.value = true
  error.value = null
  try {
    const { data } = await adminFetch<InventoryItem>(`/admin/inventory/${props.item.id}`, {
      method: 'PATCH',
      body: {
        quantity_available: quantity.value,
        low_stock_threshold: threshold.value,
        ...(note.value.trim() ? { note: note.value.trim() } : {}),
      },
    })
    emit('saved', data)
    toast.success('Stock updated', `${props.item.productName} · ${variant.value} → ${data.quantityAvailable}`)
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
    title="Adjust stock"
    :description="item ? `${item.productName} · ${variant}` : undefined"
  >
    <div v-if="item" class="-mt-1 mb-4 flex items-center gap-5 border-b border-border">
      <button
        v-for="t in (['count', 'history'] as const)" :key="t"
        type="button" class="-mb-px border-b-2 pb-2 text-ui capitalize transition-colors"
        :class="view === t ? 'border-accent font-medium text-fg-strong' : 'border-transparent text-fg-muted hover:text-fg-strong'"
        @click="view = t"
      >
        {{ t }}
      </button>
    </div>

    <form v-if="item && view === 'count'" class="flex flex-col gap-4" @submit.prevent="save">
      <div>
        <label class="field-label" for="adjust-quantity">Units on the shelf</label>
        <input
          id="adjust-quantity" v-model.number="quantity" type="number" min="0" step="1"
          class="field w-full" required
        >
        <p class="mt-1.5 text-meta text-fg-faint">
          The total you counted, not the change.
          <template v-if="item.quantityReserved">
            {{ item.quantityReserved }} held for orders awaiting payment, so it can’t go below that.
          </template>
        </p>
      </div>

      <div>
        <label class="field-label" for="adjust-threshold">Low-stock threshold</label>
        <input
          id="adjust-threshold" v-model.number="threshold" type="number" min="0" step="1"
          class="field w-full" required
        >
      </div>

      <div>
        <label class="field-label" for="adjust-note">Reason <span class="text-fg-faint">(optional)</span></label>
        <input
          id="adjust-note" v-model="note" type="text" maxlength="255"
          class="field w-full" placeholder="e.g. Restock from the workshop"
        >
      </div>

      <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">
        {{ error }}
      </p>
    </form>

    <div v-else-if="item" class="max-h-96 overflow-y-auto">
      <p v-if="historyLoading" class="py-6 text-center text-meta text-fg-faint">Loading history…</p>
      <p v-else-if="historyError" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">
        Couldn’t load the history. Try again in a moment.
      </p>
      <p v-else-if="!movements.length" class="py-6 text-center text-meta text-fg-faint">
        No stock movements recorded for this variant yet.
      </p>
      <ol v-else class="divide-y divide-border">
        <li v-for="m in movements" :key="m.id" class="flex items-start justify-between gap-3 py-2.5">
          <div class="min-w-0">
            <p class="text-ui text-fg-strong">
              {{ reasonLabel[m.reason] }}
              <UiBadge v-if="m.channel === 'whatsapp'" tone="success" class="ml-1">WhatsApp</UiBadge>
            </p>
            <p class="mt-0.5 text-meta text-fg-faint">
              {{ formatRelative(m.createdAt) }}
              <template v-if="m.orderReference"> · {{ m.orderReference }}</template>
              <template v-if="m.adminName"> · {{ m.adminName }}</template>
            </p>
            <p v-if="m.note" class="mt-0.5 text-meta text-fg-muted">{{ m.note }}</p>
          </div>
          <div class="shrink-0 text-right font-mono text-meta">
            <p v-if="m.deltaAvailable" class="text-fg-strong">{{ signed(m.deltaAvailable) }} on shelf</p>
            <p v-if="m.deltaReserved" class="text-fg-muted">{{ signed(m.deltaReserved) }} held</p>
            <p class="text-fg-faint">then {{ m.quantityAvailableAfter }}, {{ m.quantityReservedAfter }} held</p>
          </div>
        </li>
      </ol>
    </div>

    <template #footer>
      <UiButton variant="secondary" size="sm" @click="open = false">{{ view === 'count' ? 'Cancel' : 'Close' }}</UiButton>
      <UiButton v-if="view === 'count'" size="sm" :loading="saving" :disabled="!valid" @click="save">Save count</UiButton>
    </template>
  </UiModal>
</template>
