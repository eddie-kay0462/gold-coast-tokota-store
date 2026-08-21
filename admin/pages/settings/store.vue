<script setup lang="ts">
import { PhArrowsClockwise, PhWarningCircle } from '@phosphor-icons/vue'
import type { CommerceSettings, FxRate } from '~/types'
import { formatRate } from '~/utils/currency'

/**
 * Store & currency, including the FX console.
 *
 * README Clarifications Needed item 2 leaves the FX provider unselected. That
 * is surfaced here rather than papered over with a plausible-looking vendor
 * name, because `FxRateService` needs a concrete API before Phase 3b and this
 * is the screen where someone would notice.
 */
useHead({ title: 'Store & currency' })

const { useAdminItem } = useAdminApi()
const { formatRelative } = useFormatters()
const { item: commerce } = useAdminItem<CommerceSettings>('commerce-settings', '/admin/settings/commerce')
const { item: fx } = useAdminItem<FxRate>('settings-fx', '/fx-rate')
</script>

<template>
  <SettingsShell title="Store & currency" description="Pricing, exchange rate and stock behaviour.">
    <UiPermissionGate capability="settings.view">
      <div v-if="commerce" class="admin-stack">
        <SettingsSection
          title="Currencies"
          description="Cedis is the base. Dollar prices are derived at read time and never stored."
        >
          <dl class="grid gap-4 sm:grid-cols-2">
            <div>
              <dt class="field-label">Base currency</dt>
              <dd class="text-ui text-fg-strong">Ghana Cedi (GHS)</dd>
            </div>
            <div>
              <dt class="field-label">Foreign currency</dt>
              <dd class="text-ui text-fg-strong">US Dollar (USD)</dd>
            </div>
          </dl>
        </SettingsSection>

        <SettingsSection title="Exchange rate" description="Refreshed on a schedule and cached; the rate is locked onto each order at checkout.">
          <div v-if="fx" class="flex flex-wrap items-center gap-4">
            <div>
              <p class="text-metric font-light tracking-tight text-fg-strong">{{ formatRate(fx) }}</p>
              <p class="mt-1 text-meta text-fg-faint">
                Fetched {{ formatRelative(fx.fetchedAt) }}
                <span v-if="fx.isStale" class="text-warning">· stale</span>
              </p>
            </div>
            <UiPermissionGate capability="settings.fx" quiet>
              <UiButton variant="secondary" size="sm" class="ml-auto">
                <PhArrowsClockwise :size="16" />
                Refresh now
              </UiButton>
            </UiPermissionGate>
          </div>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <p class="field-label">Provider</p>
              <p class="flex items-start gap-2 text-ui" :class="commerce.fxProvider === 'Not yet selected' ? 'text-warning' : 'text-fg-strong'">
                <PhWarningCircle v-if="commerce.fxProvider === 'Not yet selected'" :size="16" class="mt-0.5 shrink-0" />
                {{ commerce.fxProvider }}
              </p>
            </div>
            <div>
              <p class="field-label">Refresh cadence</p>
              <p class="text-ui text-fg-strong">Every {{ commerce.fxRefreshMinutes }} minutes</p>
            </div>
          </div>

          <p
            v-if="commerce.fxProvider === 'Not yet selected'"
            class="mt-4 rounded-lg border border-warning/30 bg-warning-soft px-3.5 py-3 text-meta text-warning"
          >
            No FX data provider has been chosen yet. The rate above is a placeholder — dollar
            prices will not be trustworthy until a provider is selected and wired into
            <code class="font-mono">FxRateService</code>.
          </p>
        </SettingsSection>

        <SettingsSection title="Stock & fulfilment" description="How long stock is held, and when we quote dispatch.">
          <dl class="grid gap-4 sm:grid-cols-2">
            <div>
              <dt class="field-label">Checkout reservation</dt>
              <dd class="text-ui text-fg-strong">{{ commerce.reservationTtlMinutes }} minutes</dd>
              <dd class="mt-0.5 text-meta text-fg-muted">
                Stock is soft-reserved for this long while a payment is in flight, then released.
              </dd>
            </div>
            <div>
              <dt class="field-label">Default low-stock threshold</dt>
              <dd class="text-ui text-fg-strong">{{ commerce.lowStockThresholdDefault }} units</dd>
            </div>
            <div>
              <dt class="field-label">Processing time</dt>
              <dd class="text-ui text-fg-strong">{{ commerce.processingHours }} hours</dd>
              <dd class="mt-0.5 text-meta text-fg-muted">Quoted to customers after payment confirmation.</dd>
            </div>
            <div>
              <dt class="field-label">Returns window</dt>
              <dd class="text-ui text-fg-strong">{{ commerce.returnsWindowDays }} days from delivery</dd>
            </div>
          </dl>
        </SettingsSection>
      </div>
    </UiPermissionGate>
  </SettingsShell>
</template>
