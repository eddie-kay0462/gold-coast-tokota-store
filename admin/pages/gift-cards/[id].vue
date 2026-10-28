<script setup lang="ts">
import { PhArrowLeft, PhEnvelopeSimple } from '@phosphor-icons/vue'
import type { GiftCard } from '~/types'
import { formatMoney } from '~/utils/currency'
import { humanise } from '~/utils/formatters'

/**
 * One card: its balance, who it's for, and every change to that balance, so a
 * customer asking "where did my credit go?" gets an answer line by line.
 *
 * Disabling a card or correcting its balance moves value, so both are an
 * Admin's (`gift_cards.issue`), and a correction needs a reason. Resending the
 * email goes to the address already on the card and is Staff work.
 */
const route = useRoute()
const { useAdminItem, adminFetch } = useAdminApi()
const { formatDate, formatDateTime } = useFormatters()
const toast = useToast()
const { item: card, refresh } = useAdminItem<GiftCard>(`gift-card-${route.params.id}`, `/admin/gift-cards/${route.params.id}`)

useHead({ title: () => card.value ? `Gift card ${card.value.code}` : 'Gift card' })

const busy = ref(false)

function failure(err: unknown, fallback: string): string | null {
  const e = err as { statusCode?: number; data?: { errors?: Record<string, string[]>; message?: string } }
  if (e.statusCode === 401 || e.statusCode === 419) return null
  return Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? fallback
}

async function setStatus(status: 'active' | 'disabled') {
  busy.value = true
  try {
    await adminFetch(`/admin/gift-cards/${route.params.id}`, { method: 'PATCH', body: { status } })
    await refresh()
    toast.success(status === 'disabled' ? 'Card disabled' : 'Card enabled', status === 'disabled' ? 'It can no longer be spent.' : undefined)
  } catch (err) {
    const message = failure(err, 'That didn’t save. Try again in a moment.')
    if (message) toast.error('Not saved', message)
  } finally {
    busy.value = false
  }
}

async function resend() {
  busy.value = true
  try {
    const { data } = await adminFetch<{ message: string }>(`/admin/gift-cards/${route.params.id}/resend`, { method: 'POST' })
    toast.success('Email sent', data.message)
  } catch (err) {
    const message = failure(err, 'The email didn’t send. Try again in a moment.')
    if (message) toast.error('Not sent', message)
  } finally {
    busy.value = false
  }
}

// --- correct the balance -------------------------------------------------

const adjustOpen = ref(false)
const adjustError = ref<string | null>(null)
const adjustDraft = reactive({ cedis: '', note: '' })

function openAdjust() {
  adjustDraft.cedis = card.value ? (card.value.balance.amount / 100).toFixed(2) : ''
  adjustDraft.note = ''
  adjustError.value = null
  adjustOpen.value = true
}

async function adjust() {
  adjustError.value = null
  const balance = Math.round(Number(adjustDraft.cedis) * 100)
  if (!Number.isFinite(balance) || balance < 0) {
    adjustError.value = 'Enter the new balance in cedis.'
    return
  }

  busy.value = true
  try {
    await adminFetch(`/admin/gift-cards/${route.params.id}`, { method: 'PATCH', body: { balance, note: adjustDraft.note } })
    adjustOpen.value = false
    await refresh()
    toast.success('Balance corrected')
  } catch (err) {
    adjustError.value = failure(err, 'That didn’t save. Try again in a moment.')
  } finally {
    busy.value = false
  }
}

const ledgerLabel: Record<string, string> = {
  issue: 'Issued',
  redeem: 'Spent on an order',
  release: 'Returned: order not paid',
  reclaim: 'Taken again: late payment',
  adjust: 'Corrected by hand',
}
</script>

<template>
  <div class="admin-stack">
    <NuxtLink to="/gift-cards" class="flex w-fit items-center gap-1.5 text-meta text-fg-muted hover:text-fg-strong">
      <PhArrowLeft :size="14" />
      Gift cards
    </NuxtLink>

    <template v-if="card">
      <UiPageHeader
        :title="card.code"
        :description="card.source === 'purchase' ? 'Gift card bought on the storefront' : 'Store credit issued in admin'"
      >
        <template #actions>
          <UiStatusBadge :status="card.isExpired ? 'expired' : card.status" />
          <UiPermissionGate capability="gift_cards.resend" quiet>
            <UiButton v-if="card.recipientEmail" variant="secondary" size="sm" :disabled="busy" @click="resend">
              <PhEnvelopeSimple :size="16" />
              Resend email
            </UiButton>
          </UiPermissionGate>
          <UiPermissionGate capability="gift_cards.issue" quiet>
            <UiButton variant="secondary" size="sm" :disabled="busy" @click="openAdjust">Correct balance</UiButton>
            <UiButton
              v-if="card.status === 'active'" variant="ghost" size="sm" :disabled="busy"
              @click="setStatus('disabled')"
            >
              Disable
            </UiButton>
            <UiButton v-else variant="ghost" size="sm" :disabled="busy" @click="setStatus('active')">Enable</UiButton>
          </UiPermissionGate>
        </template>
      </UiPageHeader>

      <SettingsSection title="Balance" :description="`Issued with ${formatMoney(card.initialBalance)}.`">
        <p class="text-metric font-light tracking-tight text-fg-strong">{{ formatMoney(card.balance) }}</p>
        <p class="mt-1 text-meta text-fg-faint">
          {{ card.expiresAt ? `Can be spent until ${formatDate(card.expiresAt)}` : 'Doesn’t expire' }}
        </p>
      </SettingsSection>

      <SettingsSection title="Details">
        <dl class="grid gap-4 sm:grid-cols-2">
          <div>
            <dt class="field-label">For</dt>
            <dd class="text-ui text-fg-strong">{{ card.recipientName ?? 'No name' }}</dd>
            <dd v-if="card.recipientEmail" class="text-meta text-fg-muted">{{ card.recipientEmail }}</dd>
          </div>
          <div v-if="card.senderName">
            <dt class="field-label">From</dt>
            <dd class="text-ui text-fg-strong">{{ card.senderName }}</dd>
          </div>
          <div v-if="card.purchaseOrderReference">
            <dt class="field-label">Bought with order</dt>
            <dd class="font-mono text-ui text-fg-strong">
              <NuxtLink :to="`/orders/${card.purchaseOrderReference}`" class="underline">{{ card.purchaseOrderReference }}</NuxtLink>
            </dd>
          </div>
          <div v-if="card.issuedBy">
            <dt class="field-label">Issued by</dt>
            <dd class="text-ui text-fg-strong">{{ card.issuedBy }}</dd>
          </div>
          <div v-if="card.message" class="sm:col-span-2">
            <dt class="field-label">Message</dt>
            <dd class="text-ui text-fg">{{ card.message }}</dd>
          </div>
          <div v-if="card.note" class="sm:col-span-2">
            <dt class="field-label">Reason</dt>
            <dd class="text-ui text-fg">{{ card.note }}</dd>
          </div>
          <div>
            <dt class="field-label">Created</dt>
            <dd class="text-ui text-fg-strong">{{ formatDate(card.createdAt) }}</dd>
          </div>
        </dl>
      </SettingsSection>

      <SettingsSection title="History" description="Every change to the balance, oldest first.">
        <ul class="divide-y divide-border">
          <li v-for="t in card.transactions ?? []" :key="t.id" class="flex flex-wrap items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
            <div class="min-w-0">
              <p class="text-ui text-fg-strong">{{ ledgerLabel[t.type] ?? humanise(t.type) }}</p>
              <p class="text-meta text-fg-faint">
                {{ formatDateTime(t.createdAt) }}
                <template v-if="t.orderReference">
                  · <NuxtLink :to="`/orders/${t.orderReference}`" class="font-mono underline">{{ t.orderReference }}</NuxtLink>
                </template>
                <template v-if="t.admin"> · {{ t.admin }}</template>
              </p>
              <p v-if="t.note" class="text-meta text-fg-muted">{{ t.note }}</p>
            </div>
            <div class="text-right">
              <p class="text-ui" :class="t.amount.amount < 0 ? 'text-fg-muted' : 'text-fg-strong'">
                {{ t.amount.amount < 0 ? '−' : '+' }}{{ formatMoney({ ...t.amount, amount: Math.abs(t.amount.amount) }) }}
              </p>
              <p class="text-meta text-fg-faint">Left: {{ formatMoney(t.balanceAfter) }}</p>
            </div>
          </li>
        </ul>
      </SettingsSection>
    </template>

    <UiModal v-model:open="adjustOpen" title="Correct the balance" description="The difference is recorded on the card's history with your reason.">
      <form class="flex flex-col gap-4" @submit.prevent="adjust">
        <UiField v-model="adjustDraft.cedis" label="New balance (GH₵)" type="number" required />
        <UiField v-model="adjustDraft.note" label="Reason" type="textarea" :rows="2" required />
        <p v-if="adjustError" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ adjustError }}</p>
        <div class="flex justify-end gap-2">
          <UiButton variant="ghost" size="sm" @click="adjustOpen = false">Cancel</UiButton>
          <UiButton type="submit" size="sm" :loading="busy">Save</UiButton>
        </div>
      </form>
    </UiModal>
  </div>
</template>
