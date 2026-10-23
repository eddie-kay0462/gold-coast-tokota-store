<script setup lang="ts">
import type { DeliverySettings, ShippingSettings, ShippingZoneRate } from '~/types'
import { formatMoney } from '~/utils/currency'

/**
 * Delivery: which courier serves each zone, what Ghana regions are charged,
 * and the two rules that change without a deploy — the Accra service polygon
 * and the free-shipping threshold.
 *
 * Rates are prices, so editing them needs `pricing.write` (§18 keeps prices
 * away from Staff). The polygon and threshold are store configuration:
 * `settings.write`. Courier credentials and on/off switches stay in the
 * server's environment and are only reported here.
 */
useHead({ title: 'Delivery' })

const { useAdminItem, useAdminList, adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()

const { item: delivery } = useAdminItem<DeliverySettings>('delivery-settings', '/admin/settings/delivery')
const { items: savedRates, refresh: refreshRates } = useAdminList<ShippingZoneRate>('shipping-rates', '/admin/shipping/rates')
const { item: shippingSettings, refresh: refreshSettings } = useAdminItem<ShippingSettings>('shipping-settings', '/admin/shipping/settings')

const CARRIERS: Record<string, string> = {
  manual: 'By hand', yango: 'Yango', swoove: 'Swoove', dhl: 'DHL Express',
}

// --- Regional rates -------------------------------------------------------

/** Cedis as typed, keyed by region. */
const rateDraft = ref<{ region: string, cedis: string, isActive: boolean }[]>([])
watch(savedRates, (rates) => {
  rateDraft.value = rates.map((r) => ({
    region: r.region,
    cedis: r.amount.amount ? (r.amount.amount / 100).toFixed(2) : '',
    isActive: r.isActive,
  }))
}, { immediate: true })

const canPrice = computed(() => can('pricing.write'))
const savingRates = ref(false)
const ratesError = ref<string | null>(null)

const ratesValid = computed(() => rateDraft.value.every((r) => {
  if (!r.isActive && r.cedis === '') return true
  const n = Number(r.cedis)
  return Number.isFinite(n) && n >= 0
}))

async function saveRates() {
  savingRates.value = true
  ratesError.value = null
  try {
    await adminFetch('/admin/shipping/rates', {
      method: 'PUT',
      body: {
        rates: rateDraft.value.map((r) => ({
          region: r.region,
          amount_ghs: r.cedis === '' ? 0 : Math.round(Number(r.cedis) * 100),
          is_active: r.isActive,
        })),
      },
    })
    await refreshRates()
    toast.success('Rates saved', 'Checkout charges the new rates from the next quote.')
  } catch (err: unknown) {
    const e = err as { statusCode?: number; data?: { errors?: Record<string, string[]>; message?: string } }
    if (e.statusCode === 401 || e.statusCode === 419) return
    ratesError.value = Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? 'Saving failed. Try again in a moment.'
  } finally {
    savingRates.value = false
  }
}

// --- Fallback rates -----------------------------------------------------
// What a zone is charged when no courier can quote, and (Ghana) what a region
// with no rate of its own pays. Prices, so pricing.write, like the regional
// rates.

const FALLBACK_ZONES = [
  { key: 'accra', label: 'Accra', hint: 'When the on-demand courier can’t quote.' },
  { key: 'ghana', label: 'Rest of Ghana', hint: 'A region with no rate below, or a courier that can’t quote.' },
  { key: 'international', label: 'International', hint: 'When DHL can’t quote.' },
] as const

const fallbackDraft = reactive<Record<'accra' | 'ghana' | 'international', string>>({ accra: '', ghana: '', international: '' })
watch(shippingSettings, (s) => {
  if (!s?.fallbackRates) return
  for (const zone of FALLBACK_ZONES) fallbackDraft[zone.key] = (s.fallbackRates[zone.key].amount / 100).toFixed(2)
}, { immediate: true })

const fallbackValid = computed(() => FALLBACK_ZONES.every(({ key }) => {
  const n = Number(fallbackDraft[key])
  return fallbackDraft[key] !== '' && Number.isFinite(n) && n >= 0
}))
const savingFallback = ref(false)
const fallbackError = ref<string | null>(null)

async function saveFallback() {
  savingFallback.value = true
  fallbackError.value = null
  try {
    await adminFetch('/admin/shipping/fallback-rates', {
      method: 'PUT',
      body: Object.fromEntries(FALLBACK_ZONES.map(({ key }) => [`${key}_ghs`, Math.round(Number(fallbackDraft[key]) * 100)])),
    })
    await refreshSettings()
    toast.success('Fallback rates saved', 'Checkout charges them from the next quote.')
  } catch (err: unknown) {
    const e = err as { statusCode?: number; data?: { errors?: Record<string, string[]>; message?: string } }
    if (e.statusCode === 401 || e.statusCode === 419) return
    fallbackError.value = Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? 'Saving failed. Try again in a moment.'
  } finally {
    savingFallback.value = false
  }
}

// --- Polygon and threshold -----------------------------------------------

const canConfigure = computed(() => can('settings.write'))
const polygonText = ref<string | null>(null)
const polygonFileName = ref<string | null>(null)
const clearPolygon = ref(false)
const thresholdCedis = ref('')
const savingSettings = ref(false)
const settingsError = ref<string | null>(null)

watch(shippingSettings, (s) => {
  thresholdCedis.value = s?.freeShippingThreshold ? (s.freeShippingThreshold.amount / 100).toFixed(2) : ''
}, { immediate: true })

async function onPolygonFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  settingsError.value = null
  if (!file) return
  polygonFileName.value = file.name
  polygonText.value = await file.text()
  clearPolygon.value = false
}

async function saveSettings() {
  settingsError.value = null

  // The polygon is only sent when it changes. The stored one comes back
  // through the app's key camelising, so re-sending it could alter it.
  const body: Record<string, unknown> = {}
  if (clearPolygon.value) body.accra_polygon = null
  if (polygonText.value !== null) {
    try {
      body.accra_polygon = JSON.parse(polygonText.value)
    } catch {
      settingsError.value = 'That file isn’t valid JSON. Export the area as GeoJSON and try again.'
      return
    }
  }

  const threshold = thresholdCedis.value.trim() === '' ? null : Math.round(Number(thresholdCedis.value) * 100)
  if (threshold !== null && (!Number.isFinite(threshold) || threshold < 1)) {
    settingsError.value = 'Enter the threshold in cedis, or leave it empty to switch free shipping off.'
    return
  }
  body.free_shipping_threshold_ghs = threshold

  savingSettings.value = true
  try {
    await adminFetch('/admin/shipping/settings', { method: 'PUT', body })
    polygonText.value = null
    polygonFileName.value = null
    clearPolygon.value = false
    await refreshSettings()
    toast.success('Delivery settings saved')
  } catch (err: unknown) {
    const e = err as { statusCode?: number; data?: { errors?: Record<string, string[]>; message?: string } }
    if (e.statusCode === 401 || e.statusCode === 419) return
    settingsError.value = Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? 'Saving failed. Try again in a moment.'
  } finally {
    savingSettings.value = false
  }
}
</script>

<template>
  <SettingsShell title="Delivery" description="Courier routing, delivery rates, and the timelines quoted at checkout.">
    <UiPermissionGate capability="settings.view">
      <div class="admin-stack">
        <SettingsSection
          v-if="delivery"
          title="Routing"
          description="Picked from where the order is going. A courier that is switched off is skipped, and its zone is dispatched by hand."
        >
          <div class="grid gap-4 sm:grid-cols-3">
            <div v-for="z in delivery.zones" :key="z.zone" class="rounded-lg border border-border p-4">
              <p class="text-ui text-fg-strong">{{ z.label }}</p>
              <div class="mt-2 flex flex-wrap gap-1.5">
                <UiBadge v-for="c in z.active" :key="c" :tone="c === 'manual' ? 'neutral' : 'info'">
                  {{ CARRIERS[c] ?? c }}
                </UiBadge>
              </div>
              <p v-if="z.configured.join() !== z.active.join()" class="mt-2 text-meta text-fg-faint">
                Configured: {{ z.configured.map((c) => CARRIERS[c] ?? c).join(', then ') }}. Not switched on yet.
              </p>
            </div>
          </div>
          <dl class="mt-4 space-y-1 text-meta text-fg-muted">
            <div>
              <dt class="inline">When a courier can’t quote at checkout:</dt>
              <dd class="ml-1 inline">
                {{ formatMoney(delivery.fallbackRates.accra) }} in Accra,
                {{ formatMoney(delivery.fallbackRates.international) }} abroad.
              </dd>
            </div>
            <div>
              <dt class="inline">Added to live courier quotes:</dt>
              <dd class="ml-1 inline">{{ delivery.quoteBufferPercent }}%</dd>
            </div>
            <div>
              <dt class="inline">Team alerts:</dt>
              <dd class="ml-1 inline">
                {{ [delivery.opsAlerts.email && 'email', delivery.opsAlerts.sms && 'SMS'].filter(Boolean).join(' and ') || 'not set up (OPS_ALERT_EMAIL / OPS_ALERT_PHONE)' }}
              </dd>
            </div>
          </dl>
        </SettingsSection>

        <SettingsSection
          title="Fallback delivery rates"
          description="Charged when a courier can’t give a live price, so checkout never stops for a courier outage."
        >
          <form @submit.prevent="saveFallback">
            <ul class="divide-y divide-border">
              <li v-for="zone in FALLBACK_ZONES" :key="zone.key" class="flex flex-wrap items-center justify-between gap-3 py-2 first:pt-0">
                <span class="min-w-0">
                  <span class="block text-ui text-fg-strong">{{ zone.label }}</span>
                  <span class="block text-meta text-fg-faint">{{ zone.hint }}</span>
                </span>
                <label class="flex items-center gap-1.5 text-meta text-fg-muted">
                  <span>GH₵</span>
                  <input
                    v-model="fallbackDraft[zone.key]" type="number" min="0" step="0.01" inputmode="decimal"
                    class="field w-28" :disabled="!canPrice" :aria-label="`${zone.label} fallback rate in cedis`"
                  >
                </label>
              </li>
            </ul>
            <p v-if="fallbackError" class="mt-3 rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">
              {{ fallbackError }}
            </p>
            <div v-if="canPrice" class="mt-4 flex justify-end">
              <UiButton type="submit" size="sm" :loading="savingFallback" :disabled="!fallbackValid">Save fallback rates</UiButton>
            </div>
            <p v-else class="mt-4 text-meta text-fg-faint">Only an Admin can change delivery prices.</p>
          </form>
        </SettingsSection>

        <SettingsSection
          title="Ghana regional rates"
          description="Charged for orders in Ghana outside the Accra zone, or without a delivery pin."
        >
          <p v-if="delivery" class="mb-3 text-meta text-fg-muted">
            <template v-if="delivery.unpricedRegions === 'refuse'">
              A region that is switched off can’t be ordered to online; checkout points the customer to WhatsApp.
            </template>
            <template v-else>
              A region that is switched off is charged the standard Ghana rate, {{ formatMoney(delivery.fallbackRates.ghana) }}.
            </template>
          </p>
          <form @submit.prevent="saveRates">
            <ul class="divide-y divide-border">
              <li v-for="r in rateDraft" :key="r.region" class="flex flex-wrap items-center justify-between gap-3 py-2 first:pt-0">
                <span class="text-ui text-fg-strong">{{ r.region }}</span>
                <span class="flex items-center gap-3">
                  <label class="flex items-center gap-1.5 text-meta text-fg-muted">
                    <span>GH₵</span>
                    <input
                      v-model="r.cedis" type="number" min="0" step="0.01" inputmode="decimal"
                      class="field w-28" :disabled="!canPrice" :aria-label="`${r.region} rate in cedis`"
                    >
                  </label>
                  <label class="flex items-center gap-1.5 text-meta text-fg-muted">
                    <input v-model="r.isActive" type="checkbox" class="size-4" :disabled="!canPrice">
                    Active
                  </label>
                </span>
              </li>
            </ul>
            <p v-if="ratesError" class="mt-3 rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">
              {{ ratesError }}
            </p>
            <div v-if="canPrice" class="mt-4 flex justify-end">
              <UiButton type="submit" size="sm" :loading="savingRates" :disabled="!ratesValid">Save rates</UiButton>
            </div>
            <p v-else class="mt-4 text-meta text-fg-faint">Only an Admin can change delivery prices.</p>
          </form>
        </SettingsSection>

        <SettingsSection
          title="Accra zone and free shipping"
          description="Orders pinned inside the Accra area go out with an on-demand courier. With no area uploaded, every Ghana order is charged its regional rate."
        >
          <div class="flex flex-col gap-5">
            <div>
              <p class="field-label">Accra service area</p>
              <p v-if="shippingSettings?.accraPolygonSummary" class="text-ui text-fg">
                {{ shippingSettings.accraPolygonSummary.polygons }} area(s),
                {{ shippingSettings.accraPolygonSummary.points }} points, from
                {{ shippingSettings.accraPolygonSummary.bounds.south.toFixed(3) }}°N–{{ shippingSettings.accraPolygonSummary.bounds.north.toFixed(3) }}°N,
                {{ shippingSettings.accraPolygonSummary.bounds.west.toFixed(3) }}°–{{ shippingSettings.accraPolygonSummary.bounds.east.toFixed(3) }}°E.
              </p>
              <p v-else class="text-ui text-fg-muted">None uploaded. There is no Accra zone yet.</p>

              <div v-if="canConfigure" class="mt-2 flex flex-wrap items-center gap-3">
                <label class="cursor-pointer text-meta text-fg-strong underline">
                  {{ shippingSettings?.accraPolygon ? 'Replace with a GeoJSON file' : 'Upload a GeoJSON file' }}
                  <input type="file" accept=".geojson,.json,application/geo+json,application/json" class="sr-only" @change="onPolygonFile">
                </label>
                <span v-if="polygonFileName" class="text-meta text-fg-muted">{{ polygonFileName }} ready to save</span>
                <label v-if="shippingSettings?.accraPolygon && !polygonFileName" class="flex items-center gap-1.5 text-meta text-fg-muted">
                  <input v-model="clearPolygon" type="checkbox" class="size-4">
                  Remove the Accra zone
                </label>
              </div>
              <p class="mt-1.5 text-meta text-fg-faint">
                A Polygon or MultiPolygon in [longitude, latitude] order, as geojson.io exports it.
              </p>
            </div>

            <div>
              <label class="field-label" for="free-threshold">Free shipping on Ghana orders from (GH₵)</label>
              <input
                id="free-threshold" v-model="thresholdCedis" type="number" min="0" step="0.01" inputmode="decimal"
                class="field w-40" :disabled="!canConfigure" placeholder="Off"
              >
              <p class="mt-1.5 text-meta text-fg-faint">
                Empty means off. The brand document sets no free-shipping rule, so only switch this on once one is agreed.
              </p>
            </div>

            <p v-if="settingsError" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">
              {{ settingsError }}
            </p>
            <div v-if="canConfigure" class="flex justify-end">
              <UiButton size="sm" :loading="savingSettings" @click="saveSettings">Save</UiButton>
            </div>
          </div>
        </SettingsSection>

        <SettingsSection
          v-if="delivery"
          title="Timelines"
          description="What the storefront quotes. These exclude customs delays."
        >
          <p class="text-ui text-fg">
            Processed within {{ delivery.processingHours }} hours of payment, then
            {{ delivery.domesticEtaLabel }} within Ghana.
          </p>
          <ul class="mt-3 divide-y divide-border">
            <li v-for="b in delivery.internationalBands" :key="b.region" class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
              <span class="text-ui text-fg-strong">{{ b.region }}</span>
              <span class="text-ui text-fg-muted">{{ b.eta }}</span>
            </li>
          </ul>
          <p class="mt-4 text-meta text-fg-muted">
            Customs duties, taxes and import fees imposed by the destination country are the
            customer's responsibility, and this is stated in the shipping policy.
          </p>
        </SettingsSection>
      </div>
    </UiPermissionGate>
  </SettingsShell>
</template>
