<script setup lang="ts">
import type { InventoryItem } from '~/types'

/**
 * Set a variant's stock count — `PATCH /admin/inventory/{id}`.
 *
 * An absolute count, not a delta: whoever is restocking has just counted the
 * shelf. Reserved units are shown but never editable — they belong to
 * checkouts in progress, and the API refuses a count below them (the 422
 * message is shown as-is, since it names the number held).
 */
const props = defineProps<{ item: InventoryItem | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [item: InventoryItem] }>()

const { adminFetch } = useAdminApi()
const toast = useToast()

const quantity = ref(0)
const threshold = ref(0)
const saving = ref(false)
const error = ref<string | null>(null)

watch(() => [props.item, open.value] as const, ([item, isOpen]) => {
  if (!item || !isOpen) return
  quantity.value = item.quantityAvailable
  threshold.value = item.lowStockThreshold
  error.value = null
}, { immediate: true })

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
      body: { quantity_available: quantity.value, low_stock_threshold: threshold.value },
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
    <form v-if="item" class="flex flex-col gap-4" @submit.prevent="save">
      <div>
        <label class="field-label" for="adjust-quantity">Units on the shelf</label>
        <input
          id="adjust-quantity" v-model.number="quantity" type="number" min="0" step="1"
          class="field w-full" required
        >
        <p class="mt-1.5 text-meta text-fg-faint">
          The total you counted, not the change.
          <template v-if="item.quantityReserved">
            {{ item.quantityReserved }} held by checkouts in progress, so it can’t go below that.
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

      <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">
        {{ error }}
      </p>
    </form>

    <template #footer>
      <UiButton variant="secondary" size="sm" @click="open = false">Cancel</UiButton>
      <UiButton size="sm" :loading="saving" :disabled="!valid" @click="save">Save count</UiButton>
    </template>
  </UiModal>
</template>
