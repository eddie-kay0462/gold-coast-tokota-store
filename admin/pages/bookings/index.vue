<script setup lang="ts">
import { PhCaretDown } from '@phosphor-icons/vue'
import type { Booking } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'
import { humanise } from '~/utils/formatters'

/**
 * Bookings, split by the two types README Feature 7 defines. They are not
 * variations of one thing: workshops are capacity-limited with a waitlist,
 * DIY orders are an unlimited queue that is never blocked or rejected. Tabs
 * keep that distinction visible rather than burying it in a filter.
 *
 * Workshop bookings are grouped by event: a session's own title when it has
 * one ("Halloween Sip & Paint"), else its workshop's name. Clicking any
 * booking opens it in a side view (`BookingDrawer`), the DIY reference photo
 * included.
 */
useHead({ title: 'Bookings' })

const { useAdminList, adminFetch } = useAdminApi()
const { formatDate, formatRelative } = useFormatters()
const toast = useToast()
const { items: bookings, pending, refresh } = useAdminList<Booking>('admin-bookings', '/admin/bookings', { per_page: 200 }, { allPages: true })

// `PATCH /admin/bookings/{id}`. Confirming texts the customer; waitlisted
// bookings are promoted from the Waitlist screen, which checks for a seat.
const changing = ref<number | null>(null)
async function setStatus(b: Booking, status: Booking['status'], done: string) {
  changing.value = b.id
  try {
    await adminFetch(`/admin/bookings/${b.id}`, { method: 'PATCH', body: { status } })
    toast.success(done, `${b.reference} · ${b.customerName}`)
    await refresh()
  } catch (err) {
    const message = failureMessage(err)
    if (message) toast.error('Not changed', message)
  } finally {
    changing.value = null
  }
}

const tab = ref<'workshop' | 'diy_order'>('workshop')

const active = ref<Booking | null>(null)
const drawerOpen = ref(false)
function openBooking(b: Booking) {
  active.value = b
  drawerOpen.value = true
}

// --- Workshop bookings by event ------------------------------------------
// Soonest upcoming event first; events with only past dates after, latest
// first. Inside one, bookings run by date, then by when they came in.
const todayKey = new Date().toISOString().slice(0, 10)
const eventGroups = computed(() => {
  const byEvent = new Map<string, Booking[]>()
  for (const b of r.filtered.value) {
    const name = b.eventName ?? b.workshopTypeName ?? 'No workshop'
    byEvent.set(name, [...(byEvent.get(name) ?? []), b])
  }
  return [...byEvent.entries()].map(([name, rows]) => {
    const sorted = rows.slice().sort((a, b) =>
      (a.scheduledDate ?? '').localeCompare(b.scheduledDate ?? '') || a.createdAt.localeCompare(b.createdAt))
    const next = sorted.find((b) => (b.scheduledDate ?? '') >= todayKey)?.scheduledDate ?? null
    const last = sorted.at(-1)?.scheduledDate ?? ''
    return {
      name,
      // The workshop a themed event runs under, when its name differs.
      under: sorted.find((b) => b.sessionTitle)?.workshopTypeName ?? null,
      rows: sorted,
      next,
      last,
      people: rows.filter((b) => b.status === 'pending' || b.status === 'confirmed').reduce((n, b) => n + (b.attendeeCount ?? 0), 0),
      waiting: rows.filter((b) => b.status === 'waitlisted').length,
      sessions: new Set(rows.map((b) => b.workshopSessionId)).size,
    }
  }).sort((a, b) => {
    if (a.next && b.next) return a.next.localeCompare(b.next)
    if (a.next || b.next) return a.next ? -1 : 1
    return b.last.localeCompare(a.last)
  })
})
const closedEvents = ref(new Set<string>())
function toggleEvent(name: string) {
  const next = new Set(closedEvents.value)
  if (next.has(name)) next.delete(name)
  else next.add(name)
  closedEvents.value = next
}
const visible = computed(() => bookings.value.filter((b) => b.type === tab.value))

const r = useResource<Booking>(visible, {
  searchFields: ['reference', 'customerName', 'customerEmail'],
  defaultSort: 'createdAt',
  defaultDir: 'desc',
})

const statusOptions = [
  { value: 'all', label: 'All statuses' },
  ...['pending', 'confirmed', 'waitlisted', 'completed', 'cancelled']
    .map((s) => ({ value: s, label: humanise(s) })),
]

const workshopColumns: Column<Booking>[] = [
  { key: 'reference', label: 'Booking' },
  { key: 'customerName', label: 'Customer' },
  { key: 'scheduledDate', label: 'Date' },
  { key: 'attendeeCount', label: 'People', align: 'right' },
  { key: 'status', label: 'Status' },
]
const diyColumns: Column<Booking>[] = [
  { key: 'reference', label: 'Order', sortable: true },
  { key: 'customerName', label: 'Customer', sortable: true },
  { key: 'diySpecs', label: 'Specification' },
  { key: 'scheduledDate', label: 'Target date', sortable: true },
  { key: 'status', label: 'Status', sortable: true },
]

const counts = computed(() => ({
  workshop: bookings.value.filter((b) => b.type === 'workshop').length,
  diy_order: bookings.value.filter((b) => b.type === 'diy_order').length,
}))
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Bookings" description="Workshop places and custom DIY sandal orders." />

    <div class="flex items-center gap-5 border-b border-border">
      <button
        v-for="t in (['workshop', 'diy_order'] as const)" :key="t"
        type="button" class="-mb-px border-b-2 pb-2.5 text-ui transition-colors"
        :class="tab === t ? 'border-accent font-medium text-fg-strong' : 'border-transparent text-fg-muted hover:text-fg-strong'"
        @click="tab = t"
      >
        {{ t === 'workshop' ? 'Workshops' : 'DIY orders' }}
        <span class="ml-1.5 text-meta text-fg-faint">{{ counts[t] }}</span>
      </button>
    </div>

    <p v-if="tab === 'diy_order'" class="text-ui text-fg-muted">
      DIY orders are queue-based. They are never capacity-limited or turned away. Customers see
      the turnaround estimate for their order type at submission.
    </p>

    <UiToolbar
      v-model:search="r.search.value" :active-filter-count="r.activeFilterCount.value"
      placeholder="Search by reference, name or email…" @clear-filters="r.clearFilters()"
    >
      <template #actions>
        <UiSelect
          :model-value="r.filters.value.status ?? 'all'" :options="statusOptions" class="w-44"
          @update:model-value="r.setFilter('status', $event)"
        />
      </template>
    </UiToolbar>

    <template v-if="tab === 'workshop'">
      <div v-if="pending" class="h-40 animate-pulse rounded-lg bg-bg-sunken" />
      <div v-else-if="!eventGroups.length" class="card">
        <UiEmptyState title="No bookings match" />
      </div>
      <section v-for="g in eventGroups" :key="g.name" class="card">
        <button
          type="button" class="flex w-full flex-wrap items-center gap-x-4 gap-y-1 p-4 text-left md:px-5"
          :aria-expanded="!closedEvents.has(g.name)" @click="toggleEvent(g.name)"
        >
          <span class="min-w-0 flex-1">
            <span class="block text-section font-medium text-fg-strong">{{ g.name }}</span>
            <span class="block text-meta text-fg-faint">
              <template v-if="g.under">{{ g.under }} · </template>
              {{ g.rows.length }} booking{{ g.rows.length === 1 ? '' : 's' }} across {{ g.sessions }} date{{ g.sessions === 1 ? '' : 's' }}
            </span>
          </span>
          <span v-if="g.next" class="text-meta text-fg-muted">Next {{ formatDate(g.next) }}</span>
          <UiBadge size="sm">{{ g.people }} {{ g.people === 1 ? 'person' : 'people' }} booked</UiBadge>
          <UiBadge v-if="g.waiting" tone="accent" size="sm">{{ g.waiting }} waiting</UiBadge>
          <PhCaretDown :size="16" class="shrink-0 text-fg-faint transition-transform" :class="!closedEvents.has(g.name) && 'rotate-180'" />
        </button>
        <div v-if="!closedEvents.has(g.name)" class="border-t border-border">
          <UiDataTable flush :columns="workshopColumns" :rows="g.rows" :row-click="openBooking" empty-title="No bookings">
            <template #cell-reference="{ row }">
              <span class="block font-mono text-fg-strong">{{ row.reference }}</span>
              <span class="block text-meta text-fg-faint">{{ formatRelative(row.createdAt) }}</span>
            </template>
            <template #cell-customerName="{ row }">
              <span class="block truncate text-fg">{{ row.customerName }}</span>
              <span class="block truncate text-meta text-fg-faint">{{ row.customerPhone }}</span>
            </template>
            <template #cell-attendeeCount="{ row }">
              <span class="text-fg-strong">{{ row.attendeeCount }}</span>
            </template>
            <template #cell-scheduledDate="{ row }">
              <span class="text-fg-muted">{{ row.scheduledDate ? formatDate(row.scheduledDate) : '-' }}</span>
              <span v-if="row.scheduledSlot" class="block text-meta text-fg-faint">{{ row.scheduledSlot }}</span>
            </template>
            <template #cell-status="{ row }">
              <UiStatusBadge :status="row.status" />
              <span v-if="row.waitlistPosition" class="block text-meta text-fg-faint">
                #{{ row.waitlistPosition }} in queue
              </span>
            </template>
            <template #actions="{ row }">
              <UiPermissionGate capability="bookings.update_status" quiet>
                <span class="flex justify-end gap-1">
                  <UiButton
                    v-if="row.status === 'pending'" variant="ghost" size="sm" :loading="changing === row.id"
                    @click="setStatus(row, 'confirmed', 'Booking confirmed')"
                  >Confirm</UiButton>
                  <UiButton
                    v-if="row.status === 'confirmed'" variant="ghost" size="sm" :loading="changing === row.id"
                    @click="setStatus(row, 'completed', 'Marked done')"
                  >Done</UiButton>
                  <UiButton
                    v-if="row.status === 'pending' || row.status === 'confirmed'" variant="ghost" size="sm"
                    class="text-danger" :disabled="changing === row.id"
                    @click="setStatus(row, 'cancelled', 'Booking cancelled')"
                  >Cancel</UiButton>
                </span>
              </UiPermissionGate>
            </template>
          </UiDataTable>
        </div>
      </section>
    </template>

    <UiDataTable
      v-if="tab === 'diy_order'"
      :columns="diyColumns" :rows="r.paged.value" :loading="pending" :row-click="openBooking"
      :sort="r.sort.value" :dir="r.dir.value"
      empty-title="No bookings match"
      @sort="r.toggleSort"
    >
      <template #cell-reference="{ row }">
        <span class="block font-mono text-fg-strong">{{ row.reference }}</span>
        <span class="block text-meta text-fg-faint">{{ formatRelative(row.createdAt) }}</span>
      </template>
      <template #cell-customerName="{ row }">
        <span class="block truncate text-fg">{{ row.customerName }}</span>
        <span class="block truncate text-meta text-fg-faint">{{ row.customerPhone }}</span>
      </template>
      <template #cell-attendeeCount="{ row }">
        <span class="text-fg-strong">{{ row.attendeeCount }}</span>
      </template>
      <template #cell-diySpecs="{ row }">
        <span v-if="row.diySpecs" class="text-fg-muted">
          Size {{ row.diySpecs.size ?? '-' }}<span v-if="row.diySpecs.footLengthCm"> · {{ row.diySpecs.footLengthCm }}cm</span>
          <span class="block text-meta text-fg-faint">
            {{ [row.diySpecs.colourway, row.diySpecs.soleMaterial, row.diySpecs.preferredFulfilment && humanise(row.diySpecs.preferredFulfilment)].filter(Boolean).join(' · ') }}
          </span>
          <a
            v-if="row.referenceImageUrl" :href="row.referenceImageUrl" target="_blank" rel="noopener"
            class="block text-meta text-accent-text underline-offset-4 hover:underline"
          >Reference photo</a>
        </span>
      </template>
      <template #cell-scheduledDate="{ row }">
        <span class="text-fg-muted">{{ formatDate(row.scheduledDate) }}</span>
      </template>
      <template #cell-status="{ row }">
        <UiStatusBadge :status="row.status" />
        <span v-if="row.waitlistPosition" class="block text-meta text-fg-faint">
          #{{ row.waitlistPosition }} in queue
        </span>
      </template>
      <template #actions="{ row }">
        <UiPermissionGate capability="bookings.update_status" quiet>
          <span class="flex justify-end gap-1">
            <UiButton
              v-if="row.status === 'pending'" variant="ghost" size="sm" :loading="changing === row.id"
              @click="setStatus(row, 'confirmed', 'Booking confirmed')"
            >Confirm</UiButton>
            <UiButton
              v-if="row.status === 'confirmed'" variant="ghost" size="sm" :loading="changing === row.id"
              @click="setStatus(row, 'completed', 'Marked done')"
            >Done</UiButton>
            <UiButton
              v-if="row.status === 'pending' || row.status === 'confirmed'" variant="ghost" size="sm"
              class="text-danger" :disabled="changing === row.id"
              @click="setStatus(row, 'cancelled', 'Booking cancelled')"
            >Cancel</UiButton>
          </span>
        </UiPermissionGate>
      </template>
      <template #footer>
        <UiPagination
          :page="r.page.value" :page-count="r.pageCount.value"
          :total="r.total.value" :per-page="r.perPage.value"
          @update:page="r.page.value = $event"
        />
      </template>
    </UiDataTable>

    <BookingsBookingDrawer v-model:open="drawerOpen" :booking="active" @changed="refresh()" />
  </div>
</template>
