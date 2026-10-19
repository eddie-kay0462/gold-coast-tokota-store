<script setup lang="ts">
import { PhCheckCircle, PhPlugsConnected, PhWarningCircle } from '@phosphor-icons/vue'
import type { SiteSettings, WhatsappSettings } from '~/types'

/**
 * WhatsApp configuration.
 *
 *  1. The deep link: the number and prefilled message behind every
 *     "Chat on WhatsApp" button on the storefront (README Feature 6).
 *  2. The greeting and business hours, which the inbox's automatic replies
 *     use (§14).
 *  3. The Business Cloud API connection behind the live inbox. Read-only:
 *     the credentials are environment secrets on the API, set once when the
 *     Super Admin connects the brand number through Meta (coexistence, so
 *     the phone app keeps working). This page reports whether that is done
 *     and whether Meta's webhook is arriving.
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

const { adminFetch } = useAdminApi()
const toast = useToast()
const saving = ref<'link' | 'greeting' | null>(null)

/** Partial update: each section saves only its own fields. */
async function save(section: 'link' | 'greeting') {
  const body = section === 'link'
    ? { whatsapp_number: form.number.replace(/\D/g, ''), whatsapp_default_message: form.message, business_hours: form.hours }
    : { whatsapp_greeting: form.greeting }
  saving.value = section
  try {
    await adminFetch('/admin/site-settings', { method: 'PUT', body })
    toast.success(section === 'link' ? 'WhatsApp link saved' : 'Greeting saved', 'Live on the storefront now.')
  } catch (err) {
    const e = err as { statusCode?: number; data?: { message?: string; errors?: Record<string, string[]> } }
    if (e.statusCode === 401 || e.statusCode === 419) return
    toast.error('Not saved', Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? 'Try again in a moment.')
  } finally {
    saving.value = null
  }
}

const { formatRelative } = useFormatters()

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
            “update with official number”. Worth confirming with the business before launch, because
            a wrong number here silently breaks the main ordering channel.
          </p>

          <template #footer>
            <UiPermissionGate capability="settings.write" quiet>
              <UiButton size="sm" :loading="saving === 'link'" @click="save('link')">Save</UiButton>
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
              <UiButton size="sm" :loading="saving === 'greeting'" @click="save('greeting')">Save</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection
          title="Business Cloud API"
          :description="wa?.connected ? 'Connected. The inbox sends and receives real messages.' : 'Not connected yet. The inbox works, but replies are not delivered.'"
        >
          <div
            class="flex items-start gap-3 rounded-lg border px-4 py-3.5"
            :class="wa?.connected ? 'border-success/30 bg-success-soft' : 'border-border bg-bg-sunken'"
          >
            <PhPlugsConnected :size="20" class="mt-px shrink-0" :class="wa?.connected ? 'text-success' : 'text-fg-faint'" />
            <div class="min-w-0">
              <p class="text-ui font-medium text-fg-strong">{{ wa?.connected ? 'Connected' : 'Not connected' }}</p>
              <p class="mt-1 text-meta text-fg-muted">
                <template v-if="wa?.connected">
                  Messages to the brand number arrive in the Inbox, and replies typed on the
                  brand phone appear there too.
                  <template v-if="wa?.lastWebhookAt">Last update from WhatsApp {{ formatRelative(wa.lastWebhookAt) }}.</template>
                  <template v-else>No update from WhatsApp has arrived yet. Check the webhook below in the Meta app.</template>
                </template>
                <template v-else>
                  A Super Admin connects the brand number through Meta’s Embedded Signup
                  (coexistence, so the WhatsApp Business app on the phone keeps working), then
                  the credentials are set on the API server. Until then, replies sent from the
                  Inbox are recorded and marked “not delivered”.
                </template>
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
              hint="Paste this into the Meta app’s WhatsApp webhook settings. Subscribe to messages, smb_message_echoes, history, smb_app_state_sync and message_template_status_update."
            />
          </div>

          <ul class="mt-4 flex flex-col gap-1.5 text-meta">
            <li class="flex items-center gap-2" :class="wa?.webhookSigningConfigured ? 'text-success' : 'text-warning'">
              <PhWarningCircle v-if="!wa?.webhookSigningConfigured" :size="14" />
              <PhCheckCircle v-else :size="14" />
              App secret {{ wa?.webhookSigningConfigured ? 'set: webhook deliveries are verified' : 'not set: every webhook delivery is refused' }}
            </li>
            <li class="flex items-center gap-2" :class="wa?.webhookVerifyTokenConfigured ? 'text-success' : 'text-warning'">
              <PhWarningCircle v-if="!wa?.webhookVerifyTokenConfigured" :size="14" />
              <PhCheckCircle v-else :size="14" />
              Verify token {{ wa?.webhookVerifyTokenConfigured ? 'set' : 'not set: Meta cannot verify the webhook URL' }}
            </li>
          </ul>
        </SettingsSection>
      </div>
    </UiPermissionGate>
  </SettingsShell>
</template>
