<script setup lang="ts">
import { PhCheckCircle, PhMagnifyingGlass, PhTicket, PhWarningCircle } from '@phosphor-icons/vue'
import type { Booking } from '~/types'
import { formatMoney } from '~/utils/currency'

/**
 * Check-in at the door. A ticket's QR code opens this page with `?code=`, so
 * scanning it with any phone camera lands here with the booking loaded; the
 * code printed under the QR can be typed instead.
 *
 * `GET /admin/bookings/check-in/{code}` finds the booking and `POST
 * /admin/bookings/{id}/check-in` records it. The API checks a booking in once,
 * and only when it's confirmed (paid, at a paid event), and says why when it
 * won't.
 */
useHead({ title: 'Check-in' })

const route = useRoute()
const router = useRouter()
const { adminFetch } = useAdminApi()
const { formatDate, formatDateTime } = useFormatters()

const code = ref(typeof route.query.code === 'string' ? route.query.code : '')
const booking = ref<Booking | null>(null)
const looking = ref(false)
const lookupError = ref<string | null>(null)
const checkingIn = ref(false)
const result = ref<{ ok: boolean; text: string } | null>(null)

async function lookUp() {
  const value = code.value.trim()
  if (!value) return
  looking.value = true
  lookupError.value = null
  result.value = null
  booking.value = null
  router.replace({ query: { code: value.toUpperCase() } })
  try {
    const { data } = await adminFetch<Booking>(`/admin/bookings/check-in/${encodeURIComponent(value)}`, { noFixture: true })
    booking.value = data
  } catch (err) {
    lookupError.value = failureMessage(err, 'Couldn’t look that up. Try again.')
  } finally {
    looking.value = false
  }
}

async function checkIn() {
  if (!booking.value) return
  checkingIn.value = true
  try {
    const { data } = await adminFetch<Booking>(`/admin/bookings/${booking.value.id}/check-in`, { method: 'POST' })
    booking.value = data
    result.value = { ok: true, text: `Checked in: ${data.attendeeCount ?? 1} ${data.attendeeCount === 1 ? 'person' : 'people'}.` }
  } catch (err) {
    result.value = { ok: false, text: failureMessage(err) ?? 'Not checked in.' }
  } finally {
    checkingIn.value = false
  }
}

function next() {
  code.value = ''
  booking.value = null
  result.value = null
  lookupError.value = null
  router.replace({ query: {} })
}

const paidOk = computed(() => booking.value && (booking.value.status === 'confirmed' || booking.value.status === 'completed'))

onMounted(() => { if (code.value) lookUp() })
</script>

<template>
  <div class="admin-stack mx-auto w-full max-w-xl">
    <UiPageHeader title="Check-in" description="Scan a ticket’s QR code with your phone camera, or type the code printed under it." />

    <UiPermissionGate capability="bookings.update_status">
      <form class="flex gap-2" @submit.prevent="lookUp">
        <input
          v-model="code" class="field min-w-0 flex-1 font-mono uppercase tracking-wider" placeholder="Ticket code"
          aria-label="Ticket code" autocomplete="off" autocapitalize="characters" spellcheck="false"
        >
        <UiButton type="submit" :loading="looking" :disabled="!code.trim()">
          <PhMagnifyingGlass :size="16" />
          Find
        </UiButton>
      </form>

      <p v-if="lookupError" class="rounded-lg bg-danger-soft px-3 py-2.5 text-ui text-danger" role="alert">{{ lookupError }}</p>

      <section v-if="booking" class="card card-pad flex flex-col gap-4">
        <div class="flex items-start gap-3">
          <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-bg-sunken text-fg-muted">
            <PhTicket :size="20" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-section font-medium text-fg-strong">{{ booking.customerName }}</p>
            <p class="text-ui text-fg-muted">
              {{ booking.attendeeCount ?? 1 }} {{ booking.attendeeCount === 1 ? 'person' : 'people' }} · {{ booking.reference }}
            </p>
          </div>
          <UiStatusBadge :status="booking.status" show-icon />
        </div>

        <dl class="space-y-1 border-t border-border pt-3 text-ui">
          <div class="flex justify-between gap-3">
            <dt class="text-fg-muted">Event</dt><dd class="text-right">{{ booking.eventName ?? booking.workshopTypeName ?? '-' }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-fg-muted">Date</dt>
            <dd class="text-right">{{ booking.scheduledDate ? formatDate(booking.scheduledDate) : '-' }}<template v-if="booking.scheduledSlot"> · {{ booking.scheduledSlot }}</template></dd>
          </div>
          <div v-if="booking.amountPaid" class="flex justify-between gap-3">
            <dt class="text-fg-muted">Paid</dt><dd>{{ formatMoney(booking.amountPaid) }}</dd>
          </div>
        </dl>

        <p
          v-if="booking.checkedInAt"
          class="flex items-center gap-2 rounded-lg bg-success-soft px-3 py-3 text-ui text-success" role="status"
        >
          <PhCheckCircle :size="20" class="shrink-0" />
          Checked in {{ formatDateTime(booking.checkedInAt) }}<template v-if="booking.checkedInBy"> by {{ booking.checkedInBy }}</template>
        </p>
        <p
          v-else-if="!paidOk"
          class="flex items-center gap-2 rounded-lg bg-danger-soft px-3 py-3 text-ui text-danger" role="alert"
        >
          <PhWarningCircle :size="20" class="shrink-0" />
          {{ booking.status === 'awaiting_payment' || booking.status === 'expired' ? 'Not paid for. Don’t let them in on this ticket.' : `This booking is ${booking.status.replace('_', ' ')}.` }}
        </p>
        <p
          v-if="result && !result.ok"
          class="rounded-lg bg-danger-soft px-3 py-2.5 text-ui text-danger" role="alert"
        >{{ result.text }}</p>

        <div class="flex flex-wrap gap-2">
          <UiButton v-if="paidOk && !booking.checkedInAt" block :loading="checkingIn" @click="checkIn">
            Check in {{ booking.attendeeCount ?? 1 }} {{ booking.attendeeCount === 1 ? 'person' : 'people' }}
          </UiButton>
          <UiButton variant="secondary" block @click="next">Next ticket</UiButton>
        </div>
      </section>
    </UiPermissionGate>
  </div>
</template>
