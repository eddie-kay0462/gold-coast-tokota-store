<script setup lang="ts">
import { PhPlugsConnected, PhWarningCircle } from '@phosphor-icons/vue'
import type { SiteSettings, WhatsappSettings } from '~/types'

/**
 * WhatsApp configuration.
 *
 * Two different things live here, and the distinction matters:
 *
 *  1. The deep link. Real, working, and all README Feature 6 actually
 *     specifies — a `wa.me` link driven by an owner-editable number and
 *     prefilled message.
 *  2. The Business Cloud API. Not connected. The Inbox needs it to send or
 *     receive anything, and it is new backend scope (a webhook receiver, a
 *     verified WABA, approved templates) that has not been costed.
 *
 * The fields for (2) are laid out so the eventual wiring is mechanical, but
 * nothing here pretends a connection exists.
 */
useHead({ title: 'WhatsApp' })

const { useAdminItem } = useAdminApi()
const { item: wa } = useAdminItem<WhatsappSettings>('whatsapp-settings', '/admin/settings/whatsapp')
const { item: site } = useAdminItem<SiteSettings>('whatsapp-site-settings', '/admin/site-settings')

const form = reactive({ number: '', message: '', greeting: '', hours: '' })
watchEffect(() => {
  if (!site.value) return
  Object.assign(form, {
    number: site.value.whatsappNumber,
    message: site.value.whatsappDefaultMessage,
    greeting: site.value.whatsappGreeting,
    hours: site.value.businessHours,
  })
})

const deepLink = computed(
  () => `https://wa.me/${form.number.replace(/\D/g, '')}?text=${encodeURIComponent(form.message)}`,
)
</script>

<template>
  <SettingsShell title="WhatsApp" description="The ordering channel customers actually use.">
    <UiPermissionGate capability="settings.view">
      <div class="admin-stack">
        <SettingsSection
          title="Deep link"
          description="Powers the floating button and the inline CTAs across the storefront. Changing the number here updates it everywhere, with no deploy."
        >
          <div class="grid gap-4 md:grid-cols-2">
            <UiField
              v-model="form.number" label="WhatsApp number"
              hint="International format, including the country code."
            />
            <UiField v-model="form.hours" label="Business hours" />
            <UiField
              v-model="form.message" label="Prefilled message" class="md:col-span-2"
              hint="What the customer's message box is pre-populated with when they tap through."
            />
          </div>

          <div class="mt-4 rounded-lg bg-bg-sunken p-3">
            <p class="text-meta text-fg-faint">Resulting link</p>
            <p class="mt-1 break-all font-mono text-meta text-fg-muted">{{ deepLink }}</p>
          </div>

          <p class="mt-3 flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft px-3.5 py-3 text-meta text-warning">
            <PhWarningCircle :size="16" class="mt-px shrink-0" />
            The number on file came from the brand document annotated
            “update with official number”. Worth confirming with the business before launch —
            a wrong number here silently breaks the main ordering channel.
          </p>

          <template #footer>
            <UiPermissionGate capability="settings.write" quiet>
              <UiButton size="sm">Save</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection
          title="Greeting"
          description="Sent automatically when someone opens a conversation for the first time."
        >
          <textarea
            v-model="form.greeting" rows="14" aria-label="Default greeting message"
            class="field resize-y font-normal leading-relaxed"
          />
          <template #footer>
            <UiPermissionGate capability="settings.write" quiet>
              <UiButton size="sm">Save</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection
          title="Business Cloud API"
          description="Required for the two-way Inbox. Not connected."
        >
          <div class="flex items-start gap-3 rounded-lg border border-border bg-bg-sunken px-4 py-3.5">
            <PhPlugsConnected :size="20" class="mt-px shrink-0 text-fg-faint" />
            <div class="min-w-0">
              <p class="text-ui font-medium text-fg-strong">Not connected</p>
              <p class="mt-1 text-meta text-fg-muted">
                The Inbox currently shows a simulation. Sending or receiving real messages needs
                a verified WhatsApp Business account, the Cloud API credentials below, and a
                webhook receiver on the API side — none of which exist yet. The original scope
                specified a deep link only, so this is additional work rather than a
                configuration step.
              </p>
            </div>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <UiField
              :model-value="wa?.phoneNumberId ?? ''" label="Phone number ID"
              placeholder="Not set" disabled
            />
            <UiField
              :model-value="wa?.wabaId ?? ''" label="WhatsApp Business Account ID"
              placeholder="Not set" disabled
            />
            <UiField
              :model-value="wa?.webhookUrl ?? ''" label="Webhook callback URL"
              class="md:col-span-2" disabled
              hint="Where Meta would deliver inbound messages and status updates. The endpoint does not exist yet."
            />
            <UiField
              :model-value="wa?.verifyTokenMasked ?? ''" label="Verify token"
              placeholder="Not set" disabled class="md:col-span-2"
            />
          </div>
        </SettingsSection>
      </div>
    </UiPermissionGate>
  </SettingsShell>
</template>
