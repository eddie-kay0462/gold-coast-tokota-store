<script setup lang="ts">
import { PhWarningCircle } from '@phosphor-icons/vue'
import type { InboxAutomation } from '~/types'

/**
 * The two automatic replies: the §14 greeting on a first message, and a note
 * outside business hours. Off until an Admin switches them on. Anyone at the
 * inbox can see what they say; changing them needs `inbox.automation`.
 */
const props = defineProps<{ automation: InboxAutomation }>()
const emit = defineEmits<{ (e: 'saved', value: InboxAutomation): void }>()

const { adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()

const canEdit = computed(() => can('inbox.automation'))
const form = reactive({
  autoGreeting: false, afterHoursEnabled: false, afterHoursMessage: '',
  days: [] as number[], open: '09:00', close: '17:00',
})

watch(() => props.automation, (a) => {
  Object.assign(form, {
    autoGreeting: a.autoGreeting, afterHoursEnabled: a.afterHoursEnabled, afterHoursMessage: a.afterHoursMessage ?? '',
    days: [...a.hours.days], open: a.hours.open, close: a.hours.close,
  })
}, { immediate: true })

const dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

function toggleDay(day: number) {
  form.days = form.days.includes(day) ? form.days.filter((d) => d !== day) : [...form.days, day].sort()
}

const saving = ref(false)

async function save() {
  saving.value = true
  try {
    const { data } = await adminFetch<InboxAutomation>('/admin/inbox/automation', {
      method: 'PUT',
      body: {
        auto_greeting: form.autoGreeting,
        after_hours_enabled: form.afterHoursEnabled,
        after_hours_message: form.afterHoursMessage.trim() || null,
        hours: { days: form.days, open: form.open, close: form.close },
      },
    })
    emit('saved', data)
    toast.success('Automatic replies saved')
  } catch (err) {
    const e = err as { data?: { message?: string; errors?: Record<string, string[]> } }
    toast.error('Not saved', Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? 'Try again in a moment.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="admin-stack max-w-3xl">
    <p class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft px-3.5 py-3 text-meta text-warning">
      <PhWarningCircle :size="16" class="mt-px shrink-0" />
      The WhatsApp Business app on the brand phone can send its own greeting and away messages.
      Turn those off in the app (Business tools → Greeting message / Away message) before switching
      these on, or customers will be greeted twice.
    </p>

    <section class="card card-pad">
      <label class="flex items-start gap-3">
        <input v-model="form.autoGreeting" type="checkbox" class="mt-1" :disabled="!canEdit">
        <span>
          <span class="block text-ui font-medium text-fg-strong">Greeting on a first message</span>
          <span class="block text-meta text-fg-muted">Sent when someone writes for the first time, or after 30 days of silence. The wording is the greeting on WhatsApp settings.</span>
        </span>
      </label>
      <p v-if="automation.greeting" class="mt-3 whitespace-pre-line rounded-lg bg-bg-sunken px-3 py-2.5 text-meta text-fg">{{ automation.greeting }}</p>
      <p v-else class="mt-3 text-meta text-warning">No greeting is set, so nothing would be sent. Add one on WhatsApp settings.</p>
    </section>

    <section class="card card-pad">
      <label class="flex items-start gap-3">
        <input v-model="form.afterHoursEnabled" type="checkbox" class="mt-1" :disabled="!canEdit">
        <span>
          <span class="block text-ui font-medium text-fg-strong">Note outside business hours</span>
          <span class="block text-meta text-fg-muted">
            Sent once per evening or weekend, never while a team member has replied in the last 30 minutes.
            It’s {{ automation.openNow ? 'open' : 'closed' }} now.
          </span>
        </span>
      </label>

      <div class="mt-4 flex flex-col gap-3">
        <div>
          <p class="field-label">Business hours (GMT)</p>
          <div class="flex flex-wrap items-center gap-1.5">
            <button
              v-for="(name, i) in dayNames" :key="name" type="button" :disabled="!canEdit"
              class="rounded-pill border px-2.5 py-1 text-meta"
              :class="form.days.includes(i + 1) ? 'border-accent bg-bg-sunken text-fg-strong' : 'border-border text-fg-muted'"
              :aria-pressed="form.days.includes(i + 1)"
              @click="toggleDay(i + 1)"
            >{{ name }}</button>
            <input v-model="form.open" type="time" class="field !min-h-0 w-28 py-1.5" aria-label="Opens at" :disabled="!canEdit">
            <span class="text-meta text-fg-faint">to</span>
            <input v-model="form.close" type="time" class="field !min-h-0 w-28 py-1.5" aria-label="Closes at" :disabled="!canEdit">
          </div>
          <p class="mt-1 text-meta text-fg-faint">Now: {{ automation.hoursLabel }}</p>
        </div>
        <div>
          <label class="field-label" for="after-hours-message">Message (optional)</label>
          <textarea id="after-hours-message" v-model="form.afterHoursMessage" rows="3" class="field w-full resize-y" :disabled="!canEdit" :placeholder="automation.afterHoursPreview" />
          <p class="mt-1 text-meta text-fg-faint">Left blank, the text shown greyed out above is sent, with the hours filled in.</p>
        </div>
      </div>
    </section>

    <div v-if="canEdit" class="flex justify-end">
      <UiButton size="sm" :loading="saving" @click="save">Save</UiButton>
    </div>
    <p v-else class="text-meta text-fg-faint">Automatic replies are managed by an Admin.</p>
  </div>
</template>
