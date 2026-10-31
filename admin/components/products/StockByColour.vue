<script setup lang="ts">
import { PhCaretDown, PhClockCounterClockwise } from '@phosphor-icons/vue'
import type { InventoryItem, VariantColour } from '~/types'

/**
 * A product's stock, one collapsible group per colour with its sizes inside,
 * and each count editable in place (`PATCH /admin/inventory/{id}`, the same
 * call Inventory › Adjust makes). History opens that modal for the size, which
 * also sets the low-stock threshold and takes a note.
 *
 * The count is absolute, as in Adjust: whoever is typing it has just counted
 * the shelf. The API refuses a count below what checkouts hold, and its
 * message, which names the number, is shown under the row.
 */
const props = defineProps<{ items: InventoryItem[]; colours?: VariantColour[] }>()
const emit = defineEmits<{ saved: [] }>()

const { adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()
const editable = computed(() => can('inventory.adjust'))

const sizeKey = (s = '') => (Number.isFinite(Number(s)) && s !== '' ? Number(s).toString().padStart(6, '0') : s)

const groups = computed(() => {
  const byColour = new Map<string, InventoryItem[]>()
  for (const item of props.items) {
    const colour = item.variantAttributes.colour ?? ''
    byColour.set(colour, [...(byColour.get(colour) ?? []), item])
  }
  // In the product's own colour order, then any the list doesn't name.
  const order = (props.colours ?? []).map((c) => c.name)
  return [...byColour.entries()]
    .sort(([a], [b]) => {
      const [ia, ib] = [order.indexOf(a), order.indexOf(b)]
      return (ia < 0 ? 999 : ia) - (ib < 0 ? 999 : ib) || a.localeCompare(b)
    })
    .map(([colour, rows]) => ({
      colour,
      hex: props.colours?.find((c) => c.name === colour)?.hex ?? null,
      rows: rows.slice().sort((a, b) => sizeKey(a.variantAttributes.size).localeCompare(sizeKey(b.variantAttributes.size))),
      available: rows.reduce((n, r) => n + r.quantityAvailable, 0),
      low: rows.filter((r) => r.quantityAvailable <= r.lowStockThreshold).length,
    }))
})

// Open/closed per colour. One colour (or none) starts open; more start closed.
const openColours = ref(new Set<string>())
watch(groups, (g) => {
  if (g.length === 1 && !openColours.value.size) openColours.value = new Set([g[0].colour])
}, { immediate: true })
function toggle(colour: string) {
  const next = new Set(openColours.value)
  if (next.has(colour)) next.delete(colour)
  else next.add(colour)
  openColours.value = next
}

// Counts being typed, keyed by row id; a row with no entry shows its saved count.
const drafts = reactive<Record<number, string>>({})
const errors = reactive<Record<number, string | null>>({})
const savingId = ref<number | null>(null)
const draftFor = (r: InventoryItem) => drafts[r.id] ?? String(r.quantityAvailable)
const changed = (r: InventoryItem) => drafts[r.id] !== undefined && drafts[r.id] !== String(r.quantityAvailable)
const validCount = (v: string) => /^\d+$/.test(v.trim()) && Number(v) <= 100000

async function saveRow(r: InventoryItem) {
  const value = draftFor(r).trim()
  if (!validCount(value)) {
    errors[r.id] = 'A whole number, 0 or more.'
    return
  }
  savingId.value = r.id
  errors[r.id] = null
  try {
    await adminFetch<InventoryItem>(`/admin/inventory/${r.id}`, {
      method: 'PATCH',
      body: { quantity_available: Number(value) },
    })
    delete drafts[r.id]
    toast.success('Stock updated', `${label(r)} → ${value}`)
    emit('saved')
  } catch (err) {
    errors[r.id] = failureMessage(err)
  } finally {
    savingId.value = null
  }
}
function reset(r: InventoryItem) {
  delete drafts[r.id]
  errors[r.id] = null
}

const label = (r: InventoryItem) =>
  [r.variantAttributes.colour, r.variantAttributes.size && `size ${r.variantAttributes.size}`].filter(Boolean).join(', ')

const historyItem = ref<InventoryItem | null>(null)
const historyOpen = ref(false)
function openHistory(r: InventoryItem) {
  historyItem.value = r
  historyOpen.value = true
}
</script>

<template>
  <div>
    <p v-if="!items.length" class="text-meta text-fg-faint">
      No stock rows yet. Add sizes above and save them.
    </p>
    <ul v-else class="divide-y divide-border rounded-lg border border-border">
      <li v-for="g in groups" :key="g.colour">
        <button
          type="button" class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-bg-sunken"
          :aria-expanded="openColours.has(g.colour)" @click="toggle(g.colour)"
        >
          <span
            v-if="g.colour" class="size-4 shrink-0 rounded-full border border-border"
            :style="{ background: g.hex ?? 'transparent' }"
          />
          <span class="min-w-0 flex-1 truncate text-ui font-medium text-fg-strong">{{ g.colour || 'All sizes' }}</span>
          <span class="text-meta text-fg-muted">{{ g.rows.length }} {{ g.rows.length === 1 ? 'size' : 'sizes' }}</span>
          <span class="text-ui text-fg-strong">{{ g.available }} available</span>
          <UiBadge v-if="g.low" tone="warning" size="sm">{{ g.low }} low</UiBadge>
          <PhCaretDown :size="14" class="shrink-0 text-fg-faint transition-transform" :class="openColours.has(g.colour) && 'rotate-180'" />
        </button>

        <ul v-if="openColours.has(g.colour)" class="border-t border-border bg-bg-sunken/40">
          <li v-for="r in g.rows" :key="r.id" class="px-3 py-2">
            <div class="flex flex-wrap items-center gap-3">
              <span class="min-w-0 flex-1 text-ui text-fg-strong">Size {{ r.variantAttributes.size ?? '-' }}</span>
              <span v-if="r.quantityReserved" class="text-meta text-info">{{ r.quantityReserved }} held</span>
              <input
                v-if="editable"
                :value="draftFor(r)" inputmode="numeric" class="field w-20 text-right"
                :class="r.quantityAvailable <= r.lowStockThreshold && !changed(r) && 'text-warning'"
                :aria-label="`Stock for ${label(r)}`"
                @input="drafts[r.id] = ($event.target as HTMLInputElement).value"
                @keydown.enter.prevent="changed(r) && saveRow(r)"
              >
              <span
                v-else class="w-20 text-right text-ui"
                :class="r.quantityAvailable <= r.lowStockThreshold ? 'font-medium text-warning' : 'text-fg'"
              >{{ r.quantityAvailable }}</span>
              <template v-if="changed(r)">
                <UiButton size="sm" :loading="savingId === r.id" @click="saveRow(r)">Save</UiButton>
                <UiButton variant="ghost" size="sm" @click="reset(r)">Undo</UiButton>
              </template>
              <button
                v-else-if="editable" type="button" class="toolbar-btn shrink-0"
                :aria-label="`History and threshold for ${label(r)}`" @click="openHistory(r)"
              >
                <PhClockCounterClockwise :size="16" />
              </button>
            </div>
            <p v-if="errors[r.id]" class="mt-1 text-meta text-danger" role="alert">{{ errors[r.id] }}</p>
          </li>
        </ul>
      </li>
    </ul>

    <InventoryAdjustModal v-model:open="historyOpen" :item="historyItem" @saved="emit('saved')" />
  </div>
</template>
