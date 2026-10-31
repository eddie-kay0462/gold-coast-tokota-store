<script setup lang="ts">
import { PhEnvelopeSimple, PhDeviceMobile } from '@phosphor-icons/vue'
import type { NotificationSettings } from '~/types'

/**
 * Notification triggers. Both channels are best-effort by design: README
 * Feature 8 requires a failed SMS or email to be logged without blocking the
 * order or booking it belongs to.
 */
useHead({ title: 'Notifications' })

const { useAdminItem, adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()
const { item: notifications, refresh } = useAdminItem<NotificationSettings>('notification-settings', '/admin/settings/notifications')

// Local copy of the switches, saved together. Password resets and gift cards
// aren't listed: they always go out.
const triggers = ref<NotificationSettings['triggers']>([])
watch(notifications, (n) => { triggers.value = (n?.triggers ?? []).map((t) => ({ ...t })) }, { immediate: true })
const saving = ref(false)

async function save() {
  saving.value = true
  try {
    await adminFetch('/admin/settings/notifications', {
      method: 'PUT',
      body: { triggers: triggers.value.map((t) => ({ key: t.key, email: t.email, sms: t.sms })) },
    })
    toast.success('Notifications saved', 'Messages from now on follow these switches.')
    await refresh()
  } catch (err) {
    const message = failureMessage(err)
    if (message) toast.error('Not saved', message)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <SettingsShell title="Notifications" description="What we send, over which channel.">
    <UiPermissionGate capability="settings.view">
      <div v-if="notifications" class="admin-stack">
        <SettingsSection title="Channels">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-border p-4">
              <p class="flex items-center gap-2 text-ui font-medium text-fg-strong">
                <PhEnvelopeSimple :size="18" class="text-fg-faint" />
                Email
              </p>
              <p class="mt-1.5 text-meta text-fg-muted">
                {{ notifications.emailFromName }} &lt;{{ notifications.emailFromAddress }}&gt;
              </p>
            </div>
            <div class="rounded-lg border border-border p-4">
              <p class="flex items-center gap-2 text-ui font-medium text-fg-strong">
                <PhDeviceMobile :size="18" class="text-fg-faint" />
                SMS
                <UiBadge :tone="notifications.smsEnabled ? 'success' : 'neutral'" size="sm" dot>
                  {{ notifications.smsEnabled ? 'On' : 'Off' }}
                </UiBadge>
              </p>
              <p class="mt-1.5 text-meta text-fg-muted">Fish Africa (api.letsfish.africa)</p>
            </div>
          </div>
          <p class="mt-4 rounded-lg border border-warning/30 bg-warning-soft px-3.5 py-3 text-meta text-warning">
            Fish Africa's per-network delivery rates across MTN, Telecel and AirtelTigo have not
            been independently verified, and it is not confirmed whether they provide
            delivery-status webhooks. Worth a sandbox test before relying on SMS for order
            confirmations.
          </p>
        </SettingsSection>

        <SettingsSection
          title="Triggers"
          description="Switch a message off on a channel and customers stop getting it there. Password resets and gift cards always go out. A failed send is logged and retried; it never blocks the order or booking behind it."
        >
          <table class="w-full">
            <thead class="text-left text-meta font-medium text-fg-muted">
              <tr class="border-b border-border">
                <th class="pb-2 font-medium">Event</th>
                <th class="w-20 pb-2 text-center font-medium">Email</th>
                <th class="w-20 pb-2 text-center font-medium">SMS</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in triggers" :key="t.key" class="border-b border-border last:border-0">
                <td class="py-2.5 text-ui text-fg">{{ t.label }}</td>
                <td class="py-2.5 text-center">
                  <input
                    v-model="t.email" type="checkbox" :disabled="!can('settings.write')" :aria-label="`Email for ${t.label}`"
                    class="size-4 rounded-sm border-border-strong text-accent focus:ring-accent"
                  >
                </td>
                <td class="py-2.5 text-center">
                  <input
                    v-model="t.sms" type="checkbox" :disabled="!can('settings.write')" :aria-label="`SMS for ${t.label}`"
                    class="size-4 rounded-sm border-border-strong text-accent focus:ring-accent"
                  >
                </td>
              </tr>
            </tbody>
          </table>
          <template #footer>
            <UiPermissionGate capability="settings.write" quiet>
              <UiButton size="sm" :loading="saving" @click="save">Save</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>
      </div>
    </UiPermissionGate>
  </SettingsShell>
</template>
