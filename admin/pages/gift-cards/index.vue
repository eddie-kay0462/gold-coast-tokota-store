<script setup lang="ts">
import { PhPlus } from '@phosphor-icons/vue'
import type { GiftCard } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'
import { formatMoney } from '~/utils/currency'
import { humanise } from '~/utils/formatters'

/**
 * Gift cards and store credit: one list, because they are one thing, a code
 * holding a cedi balance. A gift card was bought on the storefront; store
 * credit was issued here (§11/§21: an item unavailable after purchase).
 *
 * Search finds a card by its code, or by the name or email it was sent to,
 * which is what a customer who has lost the email can tell you.
 */
useHead({ title: 'Gift cards' })

const { useAdminList, adminFetch } = useAdminApi()
const { formatDate } = useFormatters()
const toast = useToast()
const { items: cards, pending, refresh } = useAdminList<GiftCard>('admin-gift-cards', '/admin/gift-cards', { per_page: 200 })

/** Expiry folded into status, so "expired" can be filtered on like the others. */
type Row = GiftCard & { state: string }
const rows = computed<Row[]>(() => cards.value.map((c) => ({ ...c, state: c.isExpired ? 'expired' : c.status })))

const r = useResource<Row>(rows, {
  searchFields: ['code', 'recipientName', 'recipientEmail', 'purchaseOrderReference'],
  defaultSort: 'createdAt',
  defaultDir: 'desc',
})

const sourceOptions = [
  { value: 'all', label: 'Gift cards and credit' },
  { value: 'purchase', label: 'Bought online' },
  { value: 'store_credit', label: 'Store credit' },
]
const stateOptions = [
  { value: 'all', label: 'All statuses' },
  ...['active', 'disabled', 'expired'].map((s) => ({ value: s, label: humanise(s) })),
]

const columns: Column<Row>[] = [
  { key: 'code', label: 'Code', sortable: true },
  { key: 'recipientName', label: 'For', sortable: true },
  { key: 'source', label: 'Type' },
  { key: 'balance', label: 'Balance', align: 'right' },
  { key: 'expiresAt', label: 'Expires', sortable: true },
  { key: 'state', label: 'Status', sortable: true },
]

// --- issue store credit --------------------------------------------------

const issueOpen = ref(false)
const issuing = ref(false)
const issueError = ref<string | null>(null)
const draft = reactive({ cedis: '', recipientName: '', recipientEmail: '', note: '', sendEmail: true })

function openIssue() {
  Object.assign(draft, { cedis: '', recipientName: '', recipientEmail: '', note: '', sendEmail: true })
  issueError.value = null
  issueOpen.value = true
}

async function issue() {
  issueError.value = null
  const amount = Math.round(Number(draft.cedis) * 100)
  if (!Number.isFinite(amount) || amount < 100) {
    issueError.value = 'Enter an amount in cedis, at least GH₵1.'
    return
  }

  issuing.value = true
  try {
    const { data } = await adminFetch<GiftCard>('/admin/gift-cards', {
      method: 'POST',
      body: {
        amount,
        recipient_name: draft.recipientName,
        recipient_email: draft.recipientEmail || null,
        note: draft.note,
        send_email: draft.sendEmail && !!draft.recipientEmail,
      },
    })
    issueOpen.value = false
    await refresh()
    toast.success('Store credit issued', draft.sendEmail && draft.recipientEmail ? `${data.code}, emailed to ${draft.recipientEmail}.` : data.code)
    await navigateTo(`/gift-cards/${data.id}`)
  } catch (err: unknown) {
    const e = err as { statusCode?: number; data?: { errors?: Record<string, string[]>; message?: string } }
    if (e.statusCode === 401 || e.statusCode === 419) return
    issueError.value = Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? 'Issuing failed. Try again in a moment.'
  } finally {
    issuing.value = false
  }
}
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader
      title="Gift cards"
      description="Gift cards bought on the storefront, and store credit issued here. Balances are in cedis."
    >
      <template #actions>
        <UiPermissionGate capability="gift_cards.issue" quiet>
          <UiButton size="sm" @click="openIssue">
            <PhPlus :size="16" />
            Issue store credit
          </UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <UiToolbar
      v-model:search="r.search.value" :active-filter-count="r.activeFilterCount.value"
      placeholder="Search by code, name, email or order…" @clear-filters="r.clearFilters()"
    >
      <template #actions>
        <UiSelect
          :model-value="r.filters.value.source ?? 'all'" :options="sourceOptions" class="w-48"
          @update:model-value="r.setFilter('source', $event)"
        />
        <UiSelect
          :model-value="r.filters.value.state ?? 'all'" :options="stateOptions" class="w-40"
          @update:model-value="r.setFilter('state', $event)"
        />
      </template>
    </UiToolbar>

    <UiDataTable
      :columns="columns" :rows="r.paged.value" :loading="pending"
      :sort="r.sort.value" :dir="r.dir.value"
      :row-link="(row) => `/gift-cards/${row.id}`"
      empty-title="No gift cards yet"
      empty-description="Cards appear here once one is bought on the storefront or store credit is issued."
      @sort="r.toggleSort"
    >
      <template #cell-code="{ row }">
        <span class="font-mono text-fg-strong">{{ row.code }}</span>
      </template>
      <template #cell-recipientName="{ row }">
        <span class="text-fg">{{ row.recipientName ?? 'No name' }}</span>
        <span v-if="row.recipientEmail" class="block truncate text-meta text-fg-faint">{{ row.recipientEmail }}</span>
      </template>
      <template #cell-source="{ row }">
        <span class="text-fg-muted">{{ row.source === 'purchase' ? 'Gift card' : 'Store credit' }}</span>
        <span v-if="row.purchaseOrderReference" class="block font-mono text-meta text-fg-faint">{{ row.purchaseOrderReference }}</span>
      </template>
      <template #cell-balance="{ row }">
        <span class="text-fg-strong">{{ formatMoney(row.balance) }}</span>
        <span class="block text-meta text-fg-faint">of {{ formatMoney(row.initialBalance) }}</span>
      </template>
      <template #cell-expiresAt="{ row }">
        <span class="text-fg-muted">{{ row.expiresAt ? formatDate(row.expiresAt) : 'Never' }}</span>
      </template>
      <template #cell-state="{ row }">
        <UiStatusBadge :status="row.state" />
      </template>
      <template #footer>
        <UiPagination
          :page="r.page.value" :page-count="r.pageCount.value"
          :total="r.total.value" :per-page="r.perPage.value"
          @update:page="r.page.value = $event"
        />
      </template>
    </UiDataTable>

    <UiModal
      v-model:open="issueOpen" title="Issue store credit"
      description="A code with a cedi balance, spent at checkout like a gift card."
    >
      <form class="flex flex-col gap-4" @submit.prevent="issue">
        <UiField v-model="draft.cedis" label="Amount (GH₵)" type="number" required />
        <UiField v-model="draft.recipientName" label="Customer name" required />
        <UiField v-model="draft.recipientEmail" label="Customer email" type="email" hint="Where the code is sent." />
        <UiField
          v-model="draft.note" label="Reason" type="textarea" :rows="2" required
          hint="Kept on the card's history, e.g. the order whose item became unavailable."
        />
        <label class="flex items-center gap-2 text-ui text-fg">
          <input v-model="draft.sendEmail" type="checkbox" class="size-4" :disabled="!draft.recipientEmail">
          Email the code to the customer
        </label>
        <p v-if="issueError" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ issueError }}</p>
        <div class="flex justify-end gap-2">
          <UiButton variant="ghost" size="sm" @click="issueOpen = false">Cancel</UiButton>
          <UiButton type="submit" size="sm" :loading="issuing">Issue</UiButton>
        </div>
      </form>
    </UiModal>
  </div>
</template>
