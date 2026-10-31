<script setup lang="ts">
import { PhArrowSquareOut, PhChatsTeardrop, PhEnvelopeSimple, PhX } from '@phosphor-icons/vue'
import type { Booking, BookingStatus } from '~/types'
import { humanise } from '~/utils/formatters'

/**
 * A booking in a side view, like an order's: who, what, the DIY reference
 * photo at a size you can actually judge, everything else the form sent, and
 * the actions. Status changes go through `PATCH /admin/bookings/{id}`, the
 * same as the list's buttons; confirming texts and emails the customer.
 *
 * A waitlisted booking is promoted from the Waitlist screen, which checks for
 * a free seat first, so it's linked rather than offered here.
 */
const props = defineProps<{ booking: Booking | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ changed: [] }>()

const { adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()
const { formatDate, formatDateTime } = useFormatters()
useBodyScrollLock(open)

// Held locally so a status change shows at once.
const status = ref<BookingStatus | null>(null)
watch(() => props.booking?.status, (s) => { status.value = s ?? null }, { immediate: true })
const confirmingCancel = ref(false)
watch(open, () => { confirmingCancel.value = false })

const changing = ref<BookingStatus | null>(null)
async function setStatus(next: BookingStatus, done: string) {
  if (!props.booking) return
  changing.value = next
  try {
    await adminFetch(`/admin/bookings/${props.booking.id}`, { method: 'PATCH', body: { status: next } })
    status.value = next
    confirmingCancel.value = false
    toast.success(done, `${props.booking.reference} · ${props.booking.customerName}`)
    emit('changed')
  } catch (err) {
    const message = failureMessage(err)
    if (message) toast.error('Not changed', message)
  } finally {
    changing.value = null
  }
}

const isDiy = computed(() => props.booking?.type === 'diy_order')
// Bookings from before the form asked for a country code hold a local number
// ("0241234567"); a Ghanaian one is read as +233.
const whatsappUrl = computed(() => {
  let digits = (props.booking?.customerPhone ?? '').replace(/\D/g, '')
  if (/^0\d{9}$/.test(digits)) digits = `233${digits.slice(1)}`
  return digits ? `https://wa.me/${digits}` : null
})
// Browsers can't show HEIC, so those get a link instead of a picture.
const photoShows = computed(() => !!props.booking?.referenceImageUrl && !/\.(heic|heif)(\?|$)/i.test(props.booking.referenceImageUrl))

// Anything the form sent that isn't shown above, so nothing is hidden. The
// keys arrive camelCased, like every API field in the admin.
const SHOWN = new Set([
  'name', 'firstName', 'lastName', 'email', 'phone', 'attendeeCount', 'size', 'footLength', 'fulfilment', 'referenceImage',
])
const otherDetails = computed(() =>
  Object.entries(props.booking?.details ?? {})
    .filter(([k, v]) => !SHOWN.has(k) && v !== null && v !== '' && typeof v !== 'object')
    .map(([k, v]) => ({ label: humanise(k.replace(/[A-Z]/g, (c) => `_${c.toLowerCase()}`)), value: String(v) })),
)

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && open.value) open.value = false
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity" leave-active-class="transition-opacity"
      enter-from-class="opacity-0" leave-to-class="opacity-0"
    >
      <div v-if="open && booking" class="fixed inset-0 z-modal bg-ink/40" @click.self="open = false">
        <div
          class="absolute inset-y-0 right-0 flex w-full max-w-lg flex-col bg-bg-elevated shadow-overlay"
          role="dialog" aria-modal="true" :aria-label="`Booking ${booking.reference}`"
        >
          <header class="flex shrink-0 items-start justify-between gap-3 border-b border-border p-4 md:p-5">
            <div class="min-w-0">
              <p class="font-mono text-section text-fg-strong">{{ booking.reference }}</p>
              <p class="mt-1 text-meta text-fg-muted">
                {{ isDiy ? 'DIY order' : 'Workshop booking' }} · sent {{ formatDateTime(booking.createdAt) }}
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <UiStatusBadge :status="status ?? booking.status" show-icon />
              <button type="button" class="toolbar-btn" aria-label="Close" @click="open = false">
                <PhX :size="18" />
              </button>
            </div>
          </header>

          <div class="min-h-0 flex-1 overflow-y-auto">
            <div class="flex flex-col gap-6 p-4 md:p-5">
              <section>
                <h3 class="text-meta uppercase tracking-wide text-fg-faint">Customer</h3>
                <p class="mt-1.5 text-ui text-fg-strong">{{ booking.customerName }}</p>
                <p class="text-ui text-fg-muted">{{ booking.customerEmail }}</p>
                <p v-if="booking.customerPhone" class="text-ui text-fg-muted">{{ booking.customerPhone }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                  <UiButton v-if="whatsappUrl" variant="secondary" size="sm" :to="whatsappUrl" target="_blank" rel="noopener">
                    <PhChatsTeardrop :size="16" />
                    WhatsApp
                  </UiButton>
                  <UiButton v-if="booking.customerEmail" variant="secondary" size="sm" :to="`mailto:${booking.customerEmail}`">
                    <PhEnvelopeSimple :size="16" />
                    Email
                  </UiButton>
                </div>
              </section>

              <!-- Workshop -->
              <section v-if="!isDiy">
                <h3 class="text-meta uppercase tracking-wide text-fg-faint">Event</h3>
                <p class="mt-1.5 text-ui text-fg-strong">{{ booking.eventName ?? booking.workshopTypeName ?? '-' }}</p>
                <p v-if="booking.sessionTitle" class="text-meta text-fg-faint">{{ booking.workshopTypeName }}</p>
                <dl class="mt-2 space-y-1 text-ui">
                  <div class="flex justify-between gap-3">
                    <dt class="text-fg-muted">Date</dt>
                    <dd>{{ booking.scheduledDate ? formatDate(booking.scheduledDate) : '-' }}<template v-if="booking.scheduledSlot"> · {{ booking.scheduledSlot }}</template></dd>
                  </div>
                  <div class="flex justify-between gap-3">
                    <dt class="text-fg-muted">People</dt><dd>{{ booking.attendeeCount ?? '-' }}</dd>
                  </div>
                  <div v-if="booking.waitlistPosition" class="flex justify-between gap-3">
                    <dt class="text-fg-muted">Waitlist</dt><dd>#{{ booking.waitlistPosition }} in the queue</dd>
                  </div>
                </dl>
              </section>

              <!-- DIY -->
              <template v-else>
                <section>
                  <h3 class="text-meta uppercase tracking-wide text-fg-faint">Reference photo</h3>
                  <a
                    v-if="booking.referenceImageUrl && photoShows" :href="booking.referenceImageUrl" target="_blank" rel="noopener"
                    class="mt-2 block overflow-hidden rounded-lg border border-border bg-bg-sunken"
                  >
                    <img :src="booking.referenceImageUrl" alt="The customer’s reference photo" class="max-h-96 w-full object-contain">
                  </a>
                  <a
                    v-else-if="booking.referenceImageUrl" :href="booking.referenceImageUrl" target="_blank" rel="noopener"
                    class="mt-1.5 inline-flex items-center gap-1.5 text-ui text-accent-text underline-offset-4 hover:underline"
                  >
                    Open the photo (HEIC, opens in a new tab) <PhArrowSquareOut :size="14" />
                  </a>
                  <p v-else class="mt-1.5 text-ui text-fg-muted">
                    None uploaded. The customer may have sent it on WhatsApp.
                  </p>
                  <p v-if="booking.referenceImageUrl" class="mt-1 text-meta text-fg-faint">The link works for a limited time; reopen the booking for a fresh one.</p>
                </section>

                <section>
                  <h3 class="text-meta uppercase tracking-wide text-fg-faint">Sandal</h3>
                  <dl class="mt-1.5 space-y-1 text-ui">
                    <div class="flex justify-between gap-3">
                      <dt class="text-fg-muted">Size</dt><dd>{{ booking.diySpecs?.size ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                      <dt class="text-fg-muted">Foot length</dt>
                      <dd>{{ booking.diySpecs?.footLengthCm ? `${booking.diySpecs.footLengthCm} cm` : '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                      <dt class="text-fg-muted">Getting it</dt>
                      <dd>{{ booking.diySpecs?.preferredFulfilment ? humanise(booking.diySpecs.preferredFulfilment) : '-' }}</dd>
                    </div>
                    <div v-if="booking.scheduledDate" class="flex justify-between gap-3">
                      <dt class="text-fg-muted">Wanted by</dt><dd>{{ formatDate(booking.scheduledDate) }}</dd>
                    </div>
                  </dl>
                </section>
              </template>

              <section v-if="otherDetails.length">
                <h3 class="text-meta uppercase tracking-wide text-fg-faint">Also sent</h3>
                <dl class="mt-1.5 space-y-1 text-ui">
                  <div v-for="d in otherDetails" :key="d.label" class="flex justify-between gap-3">
                    <dt class="text-fg-muted">{{ d.label }}</dt><dd class="min-w-0 text-right">{{ d.value }}</dd>
                  </div>
                </dl>
              </section>
            </div>
          </div>

          <footer v-if="can('bookings.update_status')" class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-border p-4 md:p-5">
            <UiButton v-if="status === 'waitlisted'" variant="secondary" size="sm" to="/waitlist">Open the waitlist</UiButton>
            <template v-if="status === 'pending' || status === 'confirmed'">
              <UiButton v-if="!confirmingCancel" variant="ghost" size="sm" class="mr-auto text-danger" @click="confirmingCancel = true">
                Cancel {{ isDiy ? 'order' : 'booking' }}
              </UiButton>
              <UiButton
                v-else variant="danger" size="sm" class="mr-auto" :loading="changing === 'cancelled'"
                @click="setStatus('cancelled', isDiy ? 'Order cancelled' : 'Booking cancelled')"
              >Cancel it</UiButton>
            </template>
            <UiButton
              v-if="status === 'pending'" size="sm" :loading="changing === 'confirmed'"
              @click="setStatus('confirmed', isDiy ? 'Order confirmed' : 'Booking confirmed')"
            >Confirm</UiButton>
            <UiButton
              v-if="status === 'confirmed'" size="sm" :loading="changing === 'completed'"
              @click="setStatus('completed', 'Marked done')"
            >{{ isDiy ? 'Mark made' : 'Mark attended' }}</UiButton>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
