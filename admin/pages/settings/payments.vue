<script setup lang="ts">
import { PhLockKey } from '@phosphor-icons/vue'
import type { PaymentSettings } from '~/types'

/**
 * Payments. Super Admin only.
 *
 * The brand PDF draws this line explicitly — Admin "cannot modify system-level
 * settings or payment credentials" — so the whole page sits behind
 * `settings.payments`, which only super_admin holds.
 *
 * Keys are shown masked and are never editable from this UI. They live as
 * Render environment secrets (README Feature 12), and a dashboard field that
 * accepts a live secret key is a dashboard field that leaks one.
 */
useHead({ title: 'Payments' })

const { useAdminItem } = useAdminApi()
const { item: payments } = useAdminItem<PaymentSettings>('payment-settings', '/admin/settings/payments')
</script>

<template>
  <SettingsShell title="Payments" description="Gateways, settlement currencies and accepted methods.">
    <UiPermissionGate capability="settings.payments">
      <div v-if="payments" class="admin-stack">
        <SettingsSection
          title="Paystack"
          description="Handles every cedi transaction. Settlement is in GHS."
        >
          <div class="flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
              <dl class="grid gap-3 sm:grid-cols-2">
                <div>
                  <dt class="field-label">Business name</dt>
                  <dd class="text-ui text-fg-strong">{{ payments.paystackBusinessName }}</dd>
                </div>
                <div>
                  <dt class="field-label">Settlement currency</dt>
                  <dd class="text-ui text-fg-strong">{{ payments.paystackSettlementCurrency }}</dd>
                </div>
                <div class="sm:col-span-2">
                  <dt class="field-label">Public key</dt>
                  <dd class="flex items-center gap-2 font-mono text-meta text-fg-muted">
                    <PhLockKey :size="14" class="shrink-0" />
                    {{ payments.paystackPublicKeyMasked }}
                  </dd>
                </div>
              </dl>
            </div>
            <UiBadge :tone="payments.paystackEnabled ? 'success' : 'neutral'" dot>
              {{ payments.paystackEnabled ? 'Live' : 'Disabled' }}
            </UiBadge>
          </div>

          <div class="mt-4 border-t border-border pt-4">
            <p class="field-label">Accepted methods</p>
            <ul class="flex flex-wrap gap-1.5">
              <li v-for="m in payments.paystackMethods" :key="m">
                <UiBadge tone="outline" size="sm">{{ m }}</UiBadge>
              </li>
            </ul>
            <p class="mt-2 text-meta text-fg-muted">
              Mobile money is the dominant channel domestically — MTN MoMo alone carries most
              cedi orders.
            </p>
          </div>
        </SettingsSection>

        <SettingsSection title="Stripe" description="Handles every dollar transaction. Settlement is in USD.">
          <div class="flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
              <dl class="grid gap-3 sm:grid-cols-2">
                <div>
                  <dt class="field-label">Settlement currency</dt>
                  <dd class="text-ui text-fg-strong">{{ payments.stripeSettlementCurrency }}</dd>
                </div>
                <div class="sm:col-span-2">
                  <dt class="field-label">Publishable key</dt>
                  <dd class="flex items-center gap-2 font-mono text-meta text-fg-muted">
                    <PhLockKey :size="14" class="shrink-0" />
                    {{ payments.stripePublishableKeyMasked }}
                  </dd>
                </div>
              </dl>
            </div>
            <UiBadge :tone="payments.stripeEnabled ? 'success' : 'neutral'" dot>
              {{ payments.stripeEnabled ? 'Live' : 'Disabled' }}
            </UiBadge>
          </div>
        </SettingsSection>

        <SettingsSection title="Where the secrets live">
          <p class="text-ui text-fg-muted">
            Secret keys are not editable here and never will be. They are stored as environment
            secrets on the hosting platform, so they never enter the database, the repository,
            or a browser session. Rotating one is a deployment change, not a settings change.
          </p>
          <p class="mt-3 text-ui text-fg-muted">
            Card details never touch our servers either — both gateways collect them on their own
            hosted fields, which is what keeps the PCI scope small.
          </p>
        </SettingsSection>
      </div>
    </UiPermissionGate>
  </SettingsShell>
</template>
