<script setup lang="ts">
import { PhCalendarDots, PhClock, PhPencilSimple, PhPlus, PhUsersThree } from '@phosphor-icons/vue'
import type { DiyTurnaroundTier, WorkshopSession, WorkshopType } from '~/types'

/**
 * Workshop catalogue.
 *
 * README models only a flat `WorkshopSession`. The business runs six named
 * experiences, each with its own recurrence, duration and capacity (brand PDF,
 * "Workshop Schedule"), and a session is an instance of one, which is what
 * lets the owner schedule another Sip & Paint without re-entering that it
 * seats 20.
 *
 * The DIY turnaround matrix lives here too, for the same reason: it is five
 * different quoted windows by order type, not the single string README's
 * SiteSetting carries.
 */
useHead({ title: 'Workshops' })

const { useAdminList, adminFetch } = useAdminApi()
const { formatDate, now } = useFormatters()
const { can } = useAuth()
const toast = useToast()
const { items: types, pending } = useAdminList<WorkshopType>('admin-workshop-types', '/admin/workshop-types')
// Upcoming only: the list is date-ascending and paged, so past sessions would
// push next month's off the first page.
const { items: sessions, refresh: refreshSessions } = useAdminList<WorkshopSession>(
  'admin-workshop-sessions', '/admin/workshop-sessions', { upcoming: true },
)
const { items: tiers, refresh: refreshTiers } = useAdminList<DiyTurnaroundTier>('admin-diy-tiers', '/admin/settings/diy-turnaround')

const todayKey = computed(() => now.value.toISOString().slice(0, 10))
const upcomingSessions = computed(() =>
  sessions.value
    .filter((s) => s.scheduledDate >= todayKey.value)
    .sort((a, b) => a.scheduledDate.localeCompare(b.scheduledDate)),
)

/** Next scheduled session per type, and how full it is. */
const upcoming = (typeId: number) =>
  upcomingSessions.value.find((s) => s.workshopTypeId === typeId) ?? null

const editingSession = ref<WorkshopSession | null>(null)
const sessionOpen = ref(false)
function openSession(s: WorkshopSession | null) {
  editingSession.value = s
  sessionOpen.value = true
}

// DIY turnaround: one tier's estimate edited in place; the API takes the
// whole list, so the rest go back unchanged.
const editingTier = ref<string | null>(null)
const tierEstimate = ref('')
const savingTier = ref(false)
function editTier(tier: DiyTurnaroundTier) {
  editingTier.value = tier.id
  tierEstimate.value = tier.estimate
}
async function saveTier() {
  if (!editingTier.value || !tierEstimate.value.trim()) return
  savingTier.value = true
  try {
    await adminFetch('/admin/settings/diy-turnaround', {
      method: 'PUT',
      body: {
        tiers: tiers.value.map((t) => ({
          id: t.id,
          label: t.label,
          estimate: t.id === editingTier.value ? tierEstimate.value.trim() : t.estimate,
          sort_order: t.sortOrder,
        })),
      },
    })
    toast.success('Turnaround saved', 'The storefront quotes it from now on.')
    editingTier.value = null
    await refreshTiers()
  } catch (err) {
    const message = failureMessage(err)
    if (message) toast.error('Not saved', message)
  } finally {
    savingTier.value = false
  }
}
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Workshops" description="The experiences on offer, their capacity, and what is next in the diary.">
      <template #actions>
        <UiButton variant="secondary" size="sm" to="/bookings/calendar">
          <PhCalendarDots :size="16" />
          Calendar
        </UiButton>
        <UiPermissionGate capability="workshops.manage" quiet>
          <UiButton size="sm" @click="openSession(null)">
            <PhPlus :size="16" />
            New session
          </UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <div v-if="pending" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
      <div v-for="i in 6" :key="i" class="h-52 animate-pulse rounded-lg bg-bg-sunken" />
    </div>

    <div v-else class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
      <article v-for="t in types" :key="t.id" class="card card-pad flex flex-col">
        <div class="flex items-start justify-between gap-3">
          <h2 class="text-section font-medium text-fg-strong">{{ t.name }}</h2>
          <UiBadge :tone="t.isActive ? 'success' : 'neutral'" dot>
            {{ t.isActive ? 'Active' : 'Paused' }}
          </UiBadge>
        </div>

        <p class="mt-2 flex-1 text-ui text-fg-muted">{{ t.description }}</p>

        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 border-t border-border pt-3.5">
          <div class="col-span-2 flex items-center gap-2 text-ui text-fg">
            <PhCalendarDots :size="16" class="shrink-0 text-fg-faint" />
            {{ t.daysLabel }}
          </div>
          <div class="col-span-2 flex items-center gap-2 text-ui text-fg">
            <PhClock :size="16" class="shrink-0 text-fg-faint" />
            {{ t.slotLabel }}
            <span class="text-fg-faint">· {{ t.durationLabel }}</span>
          </div>
          <div class="flex items-center gap-2 text-ui text-fg">
            <PhUsersThree :size="16" class="shrink-0 text-fg-faint" />
            {{ t.capacity }} places
          </div>
        </dl>

        <div v-if="upcoming(t.id)" class="mt-3.5 rounded-lg bg-bg-sunken px-3 py-2.5">
          <p class="text-meta text-fg-faint">Next session</p>
          <p class="mt-0.5 flex flex-wrap items-center gap-2 text-ui text-fg-strong">
            {{ formatDate(upcoming(t.id)!.scheduledDate) }}
            <UiBadge
              :tone="upcoming(t.id)!.occupiedSeats >= t.capacity ? 'accent' : 'neutral'"
              size="sm"
            >
              {{ upcoming(t.id)!.occupiedSeats }}/{{ t.capacity }}
              {{ upcoming(t.id)!.occupiedSeats >= t.capacity ? '· full' : '' }}
            </UiBadge>
          </p>
          <p v-if="upcoming(t.id)!.waitlistCount" class="mt-1 text-meta text-accent-text">
            {{ upcoming(t.id)!.waitlistCount }} waiting on a place
          </p>
        </div>
        <p v-else class="mt-3.5 rounded-lg bg-bg-sunken px-3 py-2.5 text-meta text-fg-faint">
          Nothing scheduled. This one runs by appointment.
        </p>
      </article>
    </div>

    <!-- Upcoming sessions: what the booking page offers seats on -->
    <section class="card">
      <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border p-4 md:p-5">
        <div>
          <h2 class="card-title">Upcoming sessions</h2>
          <p class="mt-1 text-ui text-fg-muted">
            What the booking page offers seats on. A workshop with no session here can’t be booked.
          </p>
        </div>
      </div>
      <UiEmptyState
        v-if="!upcomingSessions.length"
        title="No sessions scheduled"
        description="Add one with New session and it shows on the booking page straight away."
      />
      <ul v-else class="divide-y divide-border">
        <li v-for="s in upcomingSessions" :key="s.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 md:px-5">
          <span class="w-28 shrink-0 text-ui font-medium text-fg-strong">{{ formatDate(s.scheduledDate) }}</span>
          <span class="min-w-0 flex-1 text-ui text-fg">
            {{ s.workshopTypeName }}
            <span class="text-fg-faint">· {{ s.scheduledSlot }}</span>
          </span>
          <UiBadge :tone="s.remainingCapacity === 0 ? 'accent' : 'neutral'" size="sm">
            {{ s.occupiedSeats }}/{{ s.capacity }}{{ s.remainingCapacity === 0 ? ' · full' : '' }}
          </UiBadge>
          <span v-if="s.waitlistCount" class="text-meta text-accent-text">{{ s.waitlistCount }} waiting</span>
          <button
            v-if="can('workshops.manage')" type="button" class="toolbar-btn"
            :aria-label="`Edit the ${s.workshopTypeName} session on ${formatDate(s.scheduledDate)}`"
            @click="openSession(s)"
          >
            <PhPencilSimple :size="16" />
          </button>
        </li>
      </ul>
    </section>

    <!-- DIY turnaround matrix -->
    <section class="card">
      <div class="border-b border-border p-4 md:p-5">
        <h2 class="card-title">DIY turnaround</h2>
        <p class="mt-1 text-ui text-fg-muted">
          What the storefront quotes at submission, per order type. Editing these changes the
          estimate customers see, with no deploy.
        </p>
      </div>
      <ul class="divide-y divide-border">
        <li v-for="tier in tiers" :key="tier.id" class="flex flex-wrap items-center gap-3 px-4 py-3 md:px-5">
          <span class="min-w-0 flex-1 text-ui text-fg-strong">{{ tier.label }}</span>
          <form v-if="editingTier === tier.id" class="flex items-center gap-2" @submit.prevent="saveTier">
            <input
              v-model="tierEstimate" class="field w-56" maxlength="120" required
              :aria-label="`Turnaround for ${tier.label}`"
            >
            <UiButton size="sm" type="submit" :loading="savingTier">Save</UiButton>
            <UiButton variant="ghost" size="sm" @click="editingTier = null">Cancel</UiButton>
          </form>
          <template v-else>
            <span class="text-ui text-fg-muted">{{ tier.estimate }}</span>
            <UiPermissionGate capability="settings.write" quiet>
              <UiButton variant="ghost" size="sm" @click="editTier(tier)">Edit</UiButton>
            </UiPermissionGate>
          </template>
        </li>
      </ul>
    </section>

    <BookingsSessionModal
      v-model:open="sessionOpen" :session="editingSession" :types="types"
      @saved="refreshSessions()" @deleted="refreshSessions()"
    />
  </div>
</template>
