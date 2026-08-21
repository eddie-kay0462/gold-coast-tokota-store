<script setup lang="ts">
import type { DeliverySettings } from '~/types'

useHead({ title: 'Delivery' })

const { useAdminItem } = useAdminApi()
const { item: delivery } = useAdminItem<DeliverySettings>('delivery-settings', '/admin/settings/delivery')
</script>

<template>
  <SettingsShell title="Delivery" description="Courier routing and the timelines quoted at checkout.">
    <UiPermissionGate capability="settings.view">
      <div v-if="delivery" class="admin-stack">
        <SettingsSection
          title="Routing"
          description="Chosen from the shipping country — there is no manual override at checkout."
        >
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-border p-4">
              <UiBadge tone="info">YANGO</UiBadge>
              <p class="mt-2 text-ui text-fg-strong">Ghana addresses</p>
              <p class="mt-1 text-meta text-fg-muted">{{ delivery.domesticEtaLabel }}, nationwide.</p>
            </div>
            <div class="rounded-lg border border-border p-4">
              <UiBadge tone="neutral">DHL</UiBadge>
              <p class="mt-2 text-ui text-fg-strong">Everywhere else</p>
              <p class="mt-1 text-meta text-fg-muted">Subject to courier availability and customs.</p>
            </div>
          </div>
        </SettingsSection>

        <SettingsSection
          title="International timelines"
          description="What the storefront quotes, by destination band. These exclude customs delays."
        >
          <ul class="divide-y divide-border">
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
