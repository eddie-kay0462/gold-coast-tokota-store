<script setup lang="ts">
import { PhArrowUp, PhSkipForward } from '@phosphor-icons/vue'
import type { Booking, WorkshopSession } from '~/types'

/**
 * Waitlist.
 *
 * Grouped by session rather than shown as a flat table, because the decision
 * being made is per session: a place opened on *this* Saturday, who gets it.
 * README Feature 7 promotes the next entry automatically on cancellation and
 * lets an admin skip someone who no longer wants the slot — both actions live
 * on the row.
 */
useHead({ title: 'Waitlist' })

const { useAdminList } = useAdminApi()
const { formatDate, formatRelative } = useFormatters()
const { items: bookings, pending } = useAdminList<Booking>('waitlist-bookings', '/admin/bookings')
const { items: sessions } = useAdminList<WorkshopSession>('waitlist-sessions', '/admin/workshop-sessions')

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
      description="When a confirmed booking is cancelled the next person here is promoted automatically and notified."
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
        <UiBadge tone="accent">
          {{ g.session.confirmedCount }}/{{ g.session.capacity }} · full
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
              <UiButton variant="secondary" size="sm">
                <PhArrowUp :size="14" />
                Promote
              </UiButton>
              <UiButton variant="ghost" size="sm">
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
