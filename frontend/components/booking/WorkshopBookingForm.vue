<script setup lang="ts">
import { PhMinus as Minus, PhPlus as Plus } from '@phosphor-icons/vue'
import type { WorkshopSession } from './SessionPicker.vue'
import { useBookingStore } from '~/stores/bookings'
import { whatsappMessage } from '~/utils/whatsapp'

const config = useRuntimeConfig()
const api = useApi()
const bookingStore = useBookingStore()

/**
 * `GET /workshop-sessions` has existed on the API since the catalogue endpoints
 * landed, and nothing had ever called it; this form passed a hardcoded empty
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

/** A full session still takes bookings; the API files them as `waitlisted`. */
const isWaitlisted = computed(
  () => !!selectedSession.value && selectedSession.value.remaining_capacity <= 0,
)

const form = reactive({ attendeeCount: 1, name: '', email: '', phone: '' })

/**
 * Attendees: at least 1, and no more than the seats left in the chosen
 * session (its full capacity once it is waitlisted, since a waitlist place is
 * still for the whole party). The server also refuses anything under 1.
 */
const maxAttendees = computed(() => {
  const session = selectedSession.value
  if (!session) return 99
  return Math.max(1, session.remaining_capacity > 0 ? session.remaining_capacity : session.capacity)
})

function setAttendees(value: number) {
  const whole = Number.isFinite(value) ? Math.round(value) : 1
  form.attendeeCount = Math.min(maxAttendees.value, Math.max(1, whole))
}

function onAttendeesInput(event: Event) {
  const raw = (event.target as HTMLInputElement).value
  // Let the box be empty (or a stray "-") while typing; it's corrected on blur.
  const n = Number(raw)
  if (raw === '' || !Number.isFinite(n) || n < 1) return
  setAttendees(n)
}

/** On leaving the box, show the corrected count, even if it didn't change. */
function onAttendeesBlur(event: Event) {
  const input = event.target as HTMLInputElement
  setAttendees(Number(input.value))
  input.value = String(form.attendeeCount)
}

// A smaller session can bring the count down; never leave it above the seats.
watch(maxAttendees, () => setAttendees(form.attendeeCount))
const submitted = ref(false)
const pendingSubmit = ref(false)
const error = ref('')

/** The way out when the submit fails: same request, different channel. */
const whatsappFallbackMessage = computed(() => whatsappMessage.workshop())

const canSubmit = computed(() => !!selectedSessionId.value && !pendingSubmit.value)

async function onSubmit() {
  if (!canSubmit.value) return
  pendingSubmit.value = true
  error.value = ''

  try {
    // Contact details go inside `details`, which is where StoreBookingRequest
    // validates them. They used to be sent at the top level, where the request
    // never looked; every submission would have failed validation.
    await api('/bookings', {
      method: 'POST',
      body: {
        type: 'workshop',
        workshop_session_id: Number(selectedSessionId.value),
        details: {
          attendee_count: form.attendeeCount,
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
    error.value = 'We could not submit that request. Check your details and try again, or send it to us directly.'
  }
  finally {
    pendingSubmit.value = false
  }
}
</script>

<template>
  <div class="mt-8">
    <!-- Success replaces the form, as the approved mockup draws it. -->
    <div v-if="submitted" class="flex flex-col items-center gap-2 border-t border-line px-6 py-14 text-center">
      <p class="caps-label text-muted">Request sent</p>
      <p class="text-display-sm text-ink">Session requested</p>
      <p class="text-label text-subtle">We will confirm by email and SMS.</p>
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

      <form class="flex flex-col gap-4 border-t border-line pt-6 md:border-l md:border-t-0 md:pl-8 md:pt-0" @submit.prevent="onSubmit">
        <h3 class="caps-label text-muted">Your details</h3>

        <!-- `attendee_count` was in the form state and submitted as a silent
             default of 1, with no control anywhere. The mockup designs it. -->
        <div class="flex w-full min-w-0 flex-col gap-1.5">
          <label for="attendee_count" class="text-caption font-normal text-graphite">
            Attendees <span aria-hidden="true">*</span>
          </label>
          <div class="field-group max-w-[180px]">
            <button
              type="button"
              class="flex w-11 shrink-0 items-center justify-center border-r border-line text-graphite transition-colors hover:text-ink disabled:cursor-not-allowed disabled:text-line focus-visible:bg-surface"
              aria-label="One fewer attendee"
              :disabled="form.attendeeCount <= 1"
              @click="setAttendees(form.attendeeCount - 1)"
            >
              <Minus :size="16" />
            </button>
            <input
              id="attendee_count"
              name="attendee_count"
              type="number"
              inputmode="numeric"
              :min="1"
              :max="maxAttendees"
              :value="form.attendeeCount"
              required
              class="min-h-[44px] w-full min-w-0 flex-1 bg-transparent text-center text-body text-graphite [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
              aria-describedby="attendee_count-hint"
              @input="onAttendeesInput"
              @blur="onAttendeesBlur"
            >
            <button
              type="button"
              class="flex w-11 shrink-0 items-center justify-center border-l border-line text-graphite transition-colors hover:text-ink disabled:cursor-not-allowed disabled:text-line focus-visible:bg-surface"
              aria-label="One more attendee"
              :disabled="form.attendeeCount >= maxAttendees"
              @click="setAttendees(form.attendeeCount + 1)"
            >
              <Plus :size="16" />
            </button>
          </div>
          <p id="attendee_count-hint" class="text-caption text-muted">
            <template v-if="selectedSession">Up to {{ maxAttendees }} for this session.</template>
            <template v-else>At least 1.</template>
          </p>
        </div>
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

        <CommonInlineNotice v-if="error" variant="warning">
          {{ error }}
          <template #action>
            <CommonWhatsAppLink source="booking-error" variant="quiet" :message="whatsappFallbackMessage">
              Send it on WhatsApp instead
            </CommonWhatsAppLink>
          </template>
        </CommonInlineNotice>

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
