<script setup lang="ts">
import { PhArrowUp, PhSkipForward } from '@phosphor-icons/vue'
import type { Booking, WorkshopSession } from '~/types'

/**
 * Waitlist.
 *
 * Grouped by session rather than shown as a flat table, because the decision
 * being made is per session: a place opened on *this* Saturday, who gets it.
 * README Feature 7 promotes the next entry automatically on cancellation and
 * lets an admin skip someone who no longer wants the slot; both actions live
 * on the row.
 */
useHead({ title: 'Waitlist' })

const { useAdminList, adminFetch } = useAdminApi()
const { formatDate, formatRelative } = useFormatters()
const toast = useToast()
const { items: bookings, pending, refresh } = useAdminList<Booking>(
  'waitlist-bookings', '/admin/bookings', { status: 'waitlisted', per_page: 200 },
)
const { items: sessions, refresh: refreshSessions } = useAdminList<WorkshopSession>(
  'waitlist-sessions', '/admin/workshop-sessions', { upcoming: true },
)

// Promote is `confirmed`, which the API only allows while the session has a
// seat (409 otherwise) and which messages the customer. Skip cancels the
// entry. Neither happens on its own: a place opening doesn't promote anyone.
const changing = ref<number | null>(null)
async function decide(b: Booking, status: 'confirmed' | 'cancelled') {
  changing.value = b.id
  try {
    await adminFetch(`/admin/bookings/${b.id}`, { method: 'PATCH', body: { status } })
    toast.success(status === 'confirmed' ? 'Promoted' : 'Skipped', status === 'confirmed'
      ? `${b.customerName} has the place and has been told.`
      : `${b.customerName} is off the waitlist.`)
    await Promise.all([refresh(), refreshSessions()])
  } catch (err) {
    const message = failureMessage(err)
    if (message) toast.error('Not changed', message)
  } finally {
    changing.value = null
  }
}

const waitlisted = computed(() => bookings.value.filter((b) => b.status === 'waitlisted'))

const groups = computed(() =>
  sessions.value
    .map((s) => ({
      session: s,
      entries: waitlisted.value
        .filter((b) => b.workshopSessionId === s.id)
        .sort((a, b) => (a.waitlistPosition ?? 99) - (b.waitlistPosition ?? 99)),
    }))
    .filter((g) => g.entries.length > 0)
    .sort((a, b) => a.session.scheduledDate.localeCompare(b.session.scheduledDate)),
)
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader
      title="Waitlist"
      description="When a place opens on a session, promote the next person here. They’re messaged that it’s theirs."
    />

    <div v-if="pending" class="space-y-4">
      <div v-for="i in 3" :key="i" class="h-40 animate-pulse rounded-lg bg-bg-sunken" />
    </div>

    <div v-else-if="!groups.length" class="card">
      <UiEmptyState
        title="Nobody is waiting"
        description="Every workshop session currently has places available."
      />
    </div>

    <section v-for="g in groups" :key="g.session.id" v-else class="card">
      <header class="flex flex-wrap items-center gap-3 border-b border-border p-4 md:p-5">
        <div class="min-w-0 flex-1">
          <h2 class="card-title">{{ g.session.workshopTypeName }}</h2>
          <p class="mt-0.5 text-ui text-fg-muted">
            {{ formatDate(g.session.scheduledDate) }} · {{ g.session.scheduledSlot }}
          </p>
        </div>
        <UiBadge v-if="g.session.remainingCapacity > 0" tone="success">
          {{ g.session.remainingCapacity }} place{{ g.session.remainingCapacity === 1 ? '' : 's' }} free
        </UiBadge>
        <UiBadge v-else tone="accent">
          {{ g.session.occupiedSeats }}/{{ g.session.capacity }} · full
        </UiBadge>
      </header>

      <ol class="divide-y divide-border">
        <li v-for="b in g.entries" :key="b.id" class="flex flex-wrap items-center gap-3 px-4 py-3 md:px-5">
          <span class="flex size-7 shrink-0 items-center justify-center rounded-pill bg-bg-sunken text-meta font-medium text-fg-subtle">
            {{ b.waitlistPosition }}
          </span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-ui text-fg-strong">{{ b.customerName }}</span>
            <span class="block truncate text-meta text-fg-faint">
              {{ b.attendeeCount }} attendee{{ b.attendeeCount === 1 ? '' : 's' }} ·
              joined {{ formatRelative(b.createdAt) }}
            </span>
          </span>
          <UiPermissionGate capability="waitlist.promote" quiet>
            <span class="flex shrink-0 gap-1">
              <UiButton
                variant="secondary" size="sm" :loading="changing === b.id"
                :disabled="g.session.remainingCapacity < 1"
                :title="g.session.remainingCapacity < 1 ? 'No place free on this session yet' : undefined"
                @click="decide(b, 'confirmed')"
              >
                <PhArrowUp :size="14" />
                Promote
              </UiButton>
              <UiButton variant="ghost" size="sm" :disabled="changing === b.id" @click="decide(b, 'cancelled')">
                <PhSkipForward :size="14" />
                Skip
              </UiButton>
            </span>
          </UiPermissionGate>
        </li>
      </ol>
    </section>
  </div>
</template>
