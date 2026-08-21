<script setup lang="ts">
import { PhCaretLeft, PhCaretRight, PhPlus } from '@phosphor-icons/vue'
import type { WorkshopSession, WorkshopType } from '~/types'

/**
 * Session calendar — Figma node 14:6138.
 *
 * Month grid with a mini-calendar and a "Today's Top 3" rail, as drawn. Below
 * `lg` it becomes an agenda list: a seven-column month grid on a phone gives
 * each day about 45px, which cannot hold a session title, so it stops being a
 * calendar and becomes a smear. The agenda carries the same information in a
 * form that survives the width.
 *
 * Sessions are coloured by workshop type, not by status — you scan this to see
 * "which Saturday is the Sip & Paint", and fill state rides along as a count.
 */
useHead({ title: 'Calendar' })

const { useAdminList } = useAdminApi()
const { formatDate } = useFormatters()
const { items: sessions, pending } = useAdminList<WorkshopSession>('cal-sessions', '/admin/workshop-sessions')
const { items: types } = useAdminList<WorkshopType>('cal-types', '/admin/workshop-types')

/** Anchored to the fixture clock so the demo opens on a populated month. */
const TODAY = new Date('2026-08-21T00:00:00Z')
const cursor = ref(new Date(Date.UTC(TODAY.getUTCFullYear(), TODAY.getUTCMonth(), 1)))

const monthLabel = computed(() =>
  cursor.value.toLocaleDateString('en-GB', { month: 'long', year: 'numeric', timeZone: 'UTC' }),
)

function shiftMonth(delta: number) {
  cursor.value = new Date(Date.UTC(cursor.value.getUTCFullYear(), cursor.value.getUTCMonth() + delta, 1))
}

const ymd = (d: Date) => d.toISOString().slice(0, 10)

/** Six weeks from the Monday on or before the 1st — a stable grid height. */
const days = computed(() => {
  const first = cursor.value
  const offset = (first.getUTCDay() + 6) % 7
  const start = new Date(first.getTime() - offset * 864e5)
  return Array.from({ length: 42 }, (_, i) => {
    const date = new Date(start.getTime() + i * 864e5)
    return {
      date,
      key: ymd(date),
      dayNum: date.getUTCDate(),
      inMonth: date.getUTCMonth() === first.getUTCMonth(),
      isToday: ymd(date) === ymd(TODAY),
    }
  })
})

const byDate = computed(() => {
  const map = new Map<string, WorkshopSession[]>()
  for (const s of sessions.value) {
    const list = map.get(s.scheduledDate) ?? []
    list.push(s)
    map.set(s.scheduledDate, list)
  }
  return map
})

const sessionsOn = (key: string) => byDate.value.get(key) ?? []

/** Stable colour per workshop type, from the chart token ramp. */
const typeColour = (typeId: number) => {
  const i = types.value.findIndex((t) => t.id === typeId)
  return `rgb(var(--chart-${((i < 0 ? 0 : i) % 6) + 1}))`
}

const todaysTop = computed(() => sessionsOn(ymd(TODAY)).slice(0, 3))
const tomorrowsTop = computed(() => sessionsOn(ymd(new Date(TODAY.getTime() + 864e5))).slice(0, 3))

/** Agenda: everything from today forward in the visible month, ascending. */
const agenda = computed(() =>
  sessions.value
    .filter((s) => {
      const d = new Date(s.scheduledDate)
      return d.getUTCFullYear() === cursor.value.getUTCFullYear()
        && d.getUTCMonth() === cursor.value.getUTCMonth()
    })
    .sort((a, b) => a.scheduledDate.localeCompare(b.scheduledDate)),
)

const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Calendar" description="Workshop sessions by date, coloured by experience.">
      <template #actions>
        <UiPermissionGate capability="workshops.manage" quiet>
          <UiButton size="sm"><PhPlus :size="16" />New session</UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <div class="flex flex-col gap-4 lg:flex-row">
      <!-- Side rail (Figma 14:6138 left column) -->
      <aside class="flex shrink-0 flex-col gap-4 lg:w-64">
        <div class="card card-pad">
          <div class="flex items-center justify-between gap-2">
            <button type="button" class="toolbar-btn size-8" aria-label="Previous month" @click="shiftMonth(-1)">
              <PhCaretLeft :size="16" />
            </button>
            <p class="text-ui font-medium text-fg-strong">{{ monthLabel }}</p>
            <button type="button" class="toolbar-btn size-8" aria-label="Next month" @click="shiftMonth(1)">
              <PhCaretRight :size="16" />
            </button>
          </div>

          <div class="mt-3 grid grid-cols-7 gap-y-1 text-center">
            <span v-for="d in weekdays" :key="d" class="text-micro uppercase text-fg-faint">{{ d[0] }}</span>
            <button
              v-for="d in days" :key="d.key"
              type="button"
              class="mx-auto flex size-7 items-center justify-center rounded-pill text-meta transition-colors"
              :class="[
                d.isToday ? 'bg-accent font-medium text-accent-fg'
                  : d.inMonth ? 'text-fg hover:bg-bg-sunken' : 'text-fg-faint',
                sessionsOn(d.key).length && !d.isToday && 'font-medium text-accent-text',
              ]"
            >{{ d.dayNum }}</button>
          </div>
        </div>

        <div class="card card-pad">
          <h2 class="text-meta uppercase tracking-wide text-fg-faint">Today</h2>
          <ul v-if="todaysTop.length" class="mt-2 space-y-2">
            <li v-for="s in todaysTop" :key="s.id" class="flex items-start gap-2">
              <span class="mt-1.5 size-1.5 shrink-0 rounded-pill" :style="{ backgroundColor: typeColour(s.workshopTypeId) }" />
              <span class="min-w-0">
                <span class="block truncate text-ui text-fg">{{ s.workshopTypeName }}</span>
                <span class="block text-meta text-fg-faint">{{ s.scheduledSlot }}</span>
              </span>
            </li>
          </ul>
          <p v-else class="mt-2 text-meta text-fg-faint">Nothing scheduled today.</p>

          <h2 class="mt-4 text-meta uppercase tracking-wide text-fg-faint">Tomorrow</h2>
          <ul v-if="tomorrowsTop.length" class="mt-2 space-y-2">
            <li v-for="s in tomorrowsTop" :key="s.id" class="flex items-start gap-2">
              <span class="mt-1.5 size-1.5 shrink-0 rounded-pill" :style="{ backgroundColor: typeColour(s.workshopTypeId) }" />
              <span class="min-w-0">
                <span class="block truncate text-ui text-fg">{{ s.workshopTypeName }}</span>
                <span class="block text-meta text-fg-faint">{{ s.scheduledSlot }}</span>
              </span>
            </li>
          </ul>
          <p v-else class="mt-2 text-meta text-fg-faint">Nothing scheduled tomorrow.</p>
        </div>

        <div class="card card-pad">
          <h2 class="text-meta uppercase tracking-wide text-fg-faint">Experiences</h2>
          <ul class="mt-2 space-y-1.5">
            <li v-for="t in types" :key="t.id" class="flex items-center gap-2">
              <span class="size-2 shrink-0 rounded-pill" :style="{ backgroundColor: typeColour(t.id) }" />
              <span class="min-w-0 flex-1 truncate text-meta text-fg-muted">{{ t.name }}</span>
            </li>
          </ul>
        </div>
      </aside>

      <!-- ── Month grid, lg and up ── -->
      <div class="card hidden min-w-0 flex-1 overflow-hidden lg:block">
        <div class="grid grid-cols-7 border-b border-border">
          <span v-for="d in weekdays" :key="d" class="px-3 py-2.5 text-meta font-medium text-fg-muted">{{ d }}</span>
        </div>
        <div class="grid grid-cols-7">
          <div
            v-for="(d, i) in days" :key="d.key"
            class="min-h-[112px] border-b border-r border-border p-2"
            :class="[
              !d.inMonth && 'bg-bg-sunken',
              (i + 1) % 7 === 0 && 'border-r-0',
              i >= 35 && 'border-b-0',
            ]"
          >
            <span
              class="inline-flex size-6 items-center justify-center rounded-pill text-meta"
              :class="d.isToday
                ? 'bg-accent font-medium text-accent-fg'
                : d.inMonth ? 'text-fg' : 'text-fg-faint'"
            >{{ d.dayNum }}</span>

            <ul class="mt-1 space-y-1">
              <li v-for="s in sessionsOn(d.key).slice(0, 2)" :key="s.id">
                <span class="flex items-start gap-1.5">
                  <span class="mt-1.5 size-1.5 shrink-0 rounded-pill" :style="{ backgroundColor: typeColour(s.workshopTypeId) }" />
                  <span class="min-w-0">
                    <span class="block truncate text-meta text-fg">{{ s.workshopTypeName }}</span>
                    <span
                      class="block text-micro"
                      :class="s.confirmedCount >= s.capacity ? 'text-accent-text' : 'text-fg-faint'"
                    >
                      {{ s.confirmedCount }}/{{ s.capacity }}{{ s.confirmedCount >= s.capacity ? ' · full' : '' }}
                    </span>
                  </span>
                </span>
              </li>
              <li v-if="sessionsOn(d.key).length > 2" class="pl-3 text-micro text-accent-text">
                +{{ sessionsOn(d.key).length - 2 }} more
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- ── Agenda, below lg ── -->
      <div class="card min-w-0 flex-1 lg:hidden">
        <div class="border-b border-border p-4">
          <h2 class="card-title">{{ monthLabel }}</h2>
        </div>
        <div v-if="pending" class="space-y-2 p-4">
          <div v-for="i in 5" :key="i" class="h-14 animate-pulse rounded bg-bg-sunken" />
        </div>
        <UiEmptyState
          v-else-if="!agenda.length"
          title="Nothing scheduled"
          description="No workshop sessions fall in this month."
        />
        <ul v-else class="divide-y divide-border">
          <li v-for="s in agenda" :key="s.id" class="flex items-start gap-3 p-4">
            <span class="mt-1 size-2 shrink-0 rounded-pill" :style="{ backgroundColor: typeColour(s.workshopTypeId) }" />
            <span class="min-w-0 flex-1">
              <span class="block text-ui text-fg-strong">{{ s.workshopTypeName }}</span>
              <span class="block text-meta text-fg-muted">
                {{ formatDate(s.scheduledDate) }} · {{ s.scheduledSlot }}
              </span>
            </span>
            <UiBadge :tone="s.confirmedCount >= s.capacity ? 'accent' : 'neutral'" size="sm">
              {{ s.confirmedCount }}/{{ s.capacity }}
            </UiBadge>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
