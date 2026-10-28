<script setup lang="ts">
import type { GiftCardSettings } from '~/types'

/**
 * Gift card policy: whether cards are on sale, the amounts, how long they
 * last, and the terms customers see. All of it is the client's to set (§22:
 * nothing invented), so the defaults are only "off" and "never expires".
 *
 * Spending a code at checkout doesn't depend on the switch: store credit
 * issued while cards are off can still be used.
 */
useHead({ title: 'Gift cards' })

const { useAdminItem, adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()
const { item: settings, refresh } = useAdminItem<GiftCardSettings>('gift-card-settings', '/admin/gift-cards/settings')

const canEdit = computed(() => can('settings.write'))
const enabled = ref(false)
const amountsText = ref('')
const expiryMonths = ref('')
const terms = ref('')
const saving = ref(false)
const error = ref<string | null>(null)

watch(settings, (s) => {
  if (!s) return
  enabled.value = s.enabled
  amountsText.value = s.amounts.map((a) => String(a / 100)).join(', ')
  expiryMonths.value = s.expiryMonths ? String(s.expiryMonths) : ''
  terms.value = s.terms ?? ''
}, { immediate: true })

/** "200, 500, 1000" (cedis) → minor units, or null when something isn't a number. */
function parseAmounts(text: string): number[] | null {
  const parts = text.split(/[\s,]+/).map((p) => p.replace(/[^\d.]/g, '')).filter(Boolean)
  const amounts = parts.map((p) => Math.round(Number(p) * 100))
  return amounts.every((a) => Number.isFinite(a) && a >= 100) ? amounts : null
}

async function save() {
  error.value = null
  const amounts = parseAmounts(amountsText.value)
  if (amounts === null) {
    error.value = 'Enter the amounts in cedis, separated by commas, e.g. 200, 500, 1000.'
    return
  }
  const months = expiryMonths.value.trim() === '' ? null : Number(expiryMonths.value)
  if (months !== null && (!Number.isInteger(months) || months < 1)) {
    error.value = 'Enter the expiry in whole months, or leave it empty for no expiry.'
    return
  }

  saving.value = true
  try {
    await adminFetch('/admin/gift-cards/settings', {
      method: 'PUT',
      body: { enabled: enabled.value, amounts, expiry_months: months, terms: terms.value.trim() || null },
    })
    await refresh()
    toast.success('Gift card settings saved', enabled.value ? 'Gift cards are on sale on the storefront.' : undefined)
  } catch (err: unknown) {
    const e = err as { statusCode?: number; data?: { errors?: Record<string, string[]>; message?: string } }
    if (e.statusCode === 401 || e.statusCode === 419) return
    error.value = Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? 'Saving failed. Try again in a moment.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <SettingsShell title="Gift cards" description="Whether gift cards are on sale, and on what terms.">
    <UiPermissionGate capability="settings.view">
      <form v-if="settings" class="admin-stack" @submit.prevent="save">
        <SettingsSection
          title="On sale"
          description="While this is off, the gift card page asks customers to arrange a gift on WhatsApp. Codes already issued can still be spent."
        >
          <label class="flex items-center gap-2 text-ui text-fg-strong">
            <input v-model="enabled" type="checkbox" class="size-4" :disabled="!canEdit">
            Sell gift cards on the storefront
          </label>
        </SettingsSection>

        <SettingsSection title="Amounts" description="The values a customer can choose from, in cedis. Dollar prices convert at checkout.">
          <label class="field-label" for="gift-amounts">Amounts (GH₵)</label>
          <input
            id="gift-amounts" v-model="amountsText" class="field w-full max-w-md" :disabled="!canEdit"
            placeholder="e.g. 200, 500, 1000"
          >
          <p class="mt-1.5 text-meta text-fg-faint">Up to eight, separated by commas.</p>
        </SettingsSection>

        <SettingsSection title="Expiry" description="Applies to cards issued from now on. Cards already out keep the date they were issued with.">
          <label class="field-label" for="gift-expiry">Months until a card expires</label>
          <input
            id="gift-expiry" v-model="expiryMonths" type="number" min="1" max="120" inputmode="numeric"
            class="field w-40" :disabled="!canEdit" placeholder="Never"
          >
          <p class="mt-1.5 text-meta text-fg-faint">Empty means cards don’t expire.</p>
        </SettingsSection>

        <SettingsSection title="Terms" description="Shown on the gift card page. Nothing is shown until you write them.">
          <textarea
            v-model="terms" rows="6" class="field w-full" :disabled="!canEdit"
            placeholder="For example, whether cards can be exchanged for cash, and what happens to a lost code."
          />
        </SettingsSection>

        <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ error }}</p>
        <div v-if="canEdit" class="flex justify-end">
          <UiButton type="submit" size="sm" :loading="saving">Save</UiButton>
        </div>
      </form>
    </UiPermissionGate>
  </SettingsShell>
</template>
