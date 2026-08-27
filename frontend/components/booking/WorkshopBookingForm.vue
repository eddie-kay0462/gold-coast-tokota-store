<script setup lang="ts">
import type { WorkshopSession } from './SessionPicker.vue'
import { useBookingStore } from '~/stores/bookings'

const config = useRuntimeConfig()
const bookingStore = useBookingStore()

/**
 * `GET /workshop-sessions` has existed on the API since the catalogue endpoints
 * landed, and nothing had ever called it — this form passed a hardcoded empty
 * array, so the session list was always empty no matter what was seeded.
 */
const { data: sessions, pending } = await useAsyncData('workshop-sessions', () =>
  $fetch<{ data: WorkshopSession[] }>(`${config.public.apiBase}/workshop-sessions`)
    .then((response) => response.data)
    .catch(() => [] as WorkshopSession[]),
)

const selectedSessionId = computed({
  get: () => bookingStore.selectedWorkshopSessionId,
  set: (id: string) => {
    const session = (sessions.value ?? []).find((entry) => String(entry.id) === id)
    bookingStore.selectWorkshopSession(id, session?.remaining_capacity ?? 0)
  },
})

const selectedSession = computed(() =>
  (sessions.value ?? []).find((entry) => String(entry.id) === selectedSessionId.value) ?? null,
)

/** A full session still takes bookings — the API files them as `waitlisted`. */
const isWaitlisted = computed(
  () => !!selectedSession.value && selectedSession.value.remaining_capacity <= 0,
)

const form = reactive({ attendeeCount: '1', name: '', email: '', phone: '' })
const submitted = ref(false)
const pendingSubmit = ref(false)
const error = ref('')

const canSubmit = computed(() => !!selectedSessionId.value && !pendingSubmit.value)

async function onSubmit() {
  if (!canSubmit.value) return
  pendingSubmit.value = true
  error.value = ''

  try {
    // Contact details go inside `details`, which is where StoreBookingRequest
    // validates them. They used to be sent at the top level, where the request
    // never looked — every submission would have failed validation.
    await $fetch(`${config.public.apiBase}/bookings`, {
      method: 'POST',
      body: {
        type: 'workshop',
        workshop_session_id: Number(selectedSessionId.value),
        details: {
          attendee_count: Number(form.attendeeCount) || 1,
          name: form.name,
          email: form.email,
          phone: form.phone,
        },
      },
    })
    submitted.value = true
    bookingStore.reset()
  }
  catch {
    error.value = 'We could not submit that request. Check your details and try again, or message us on WhatsApp.'
  }
  finally {
    pendingSubmit.value = false
  }
}
</script>

<template>
  <div class="mt-8">
    <!-- Success replaces the form, as the approved mockup draws it. -->
    <div v-if="submitted" class="border border-line bg-surface px-6 py-14 text-center">
      <p class="text-display-sm font-light text-black">Session requested</p>
      <p class="mt-2 text-label text-muted">We will confirm by email and SMS.</p>
    </div>

    <div v-else class="grid gap-8 md:grid-cols-[1.1fr_1fr] md:items-start">
      <div class="flex flex-col gap-4">
        <BookingSessionPicker
          v-model="selectedSessionId"
          :sessions="sessions ?? []"
          :pending="pending"
        />
        <BookingWaitlistBanner v-if="isWaitlisted" />
      </div>

      <form class="flex flex-col gap-4 border border-line p-6" @submit.prevent="onSubmit">
        <h3 class="text-eyebrow uppercase tracking-[0.6px] text-muted">Your details</h3>

        <!-- `attendee_count` was in the form state and submitted as a silent
             default of 1, with no control anywhere. The mockup designs it. -->
        <FormsFormField
          v-model="form.attendeeCount"
          label="Attendees"
          name="attendee_count"
          type="number"
          required
        />
        <FormsFormField v-model="form.name" label="Full name" name="name" required autocomplete="name" />
        <FormsFormField v-model="form.email" label="Email address" name="email" type="email" required autocomplete="email" />
        <FormsFormField
          v-model="form.phone"
          label="Phone (WhatsApp)"
          name="phone"
          type="tel"
          required
          autocomplete="tel"
        />

        <CommonInlineNotice v-if="error" variant="warning">{{ error }}</CommonInlineNotice>

        <p v-if="!selectedSessionId" class="text-caption text-muted">
          Choose a session to continue.
        </p>

        <CommonBrandButton full type="submit" :disabled="!canSubmit">
          {{ pendingSubmit ? 'Sending…' : isWaitlisted ? 'Join the waitlist' : 'Request session' }}
        </CommonBrandButton>
      </form>
    </div>
  </div>
</template>
