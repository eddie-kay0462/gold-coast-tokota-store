<script setup lang="ts">
import { PhCalendarDots, PhClock, PhPlus, PhUsersThree } from '@phosphor-icons/vue'
import type { DiyTurnaroundTier, WorkshopSession, WorkshopType } from '~/types'

/**
 * Workshop catalogue.
 *
 * README models only a flat `WorkshopSession`. The business runs six named
 * experiences, each with its own recurrence, duration and capacity (brand PDF,
 * "Workshop Schedule"), and a session is an instance of one — which is what
 * lets the owner schedule another Sip & Paint without re-entering that it
 * seats 20.
 *
 * The DIY turnaround matrix lives here too, for the same reason: it is five
 * different quoted windows by order type, not the single string README's
 * SiteSetting carries.
 */
useHead({ title: 'Workshops' })

const { useAdminList } = useAdminApi()
const { formatDate } = useFormatters()
const { items: types, pending } = useAdminList<WorkshopType>('admin-workshop-types', '/admin/workshop-types')
const { items: sessions } = useAdminList<WorkshopSession>('admin-workshop-sessions', '/admin/workshop-sessions')
const { items: tiers } = useAdminList<DiyTurnaroundTier>('admin-diy-tiers', '/admin/settings/diy-turnaround')

/** Next scheduled session per type, and how full it is. */
const upcoming = (typeId: number) =>
  sessions.value
    .filter((s) => s.workshopTypeId === typeId && new Date(s.scheduledDate) >= new Date('2026-08-21'))
    .sort((a, b) => a.scheduledDate.localeCompare(b.scheduledDate))[0] ?? null
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
          <UiButton size="sm">
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
              :tone="upcoming(t.id)!.confirmedCount >= t.capacity ? 'accent' : 'neutral'"
              size="sm"
            >
              {{ upcoming(t.id)!.confirmedCount }}/{{ t.capacity }}
              {{ upcoming(t.id)!.confirmedCount >= t.capacity ? '· full' : '' }}
            </UiBadge>
          </p>
          <p v-if="upcoming(t.id)!.waitlistCount" class="mt-1 text-meta text-accent-text">
            {{ upcoming(t.id)!.waitlistCount }} waiting on a place
          </p>
        </div>
        <p v-else class="mt-3.5 rounded-lg bg-bg-sunken px-3 py-2.5 text-meta text-fg-faint">
          Nothing scheduled — this one runs by appointment.
        </p>
      </article>
    </div>

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
          <span class="text-ui text-fg-muted">{{ tier.estimate }}</span>
          <UiPermissionGate capability="settings.write" quiet>
            <UiButton variant="ghost" size="sm">Edit</UiButton>
          </UiPermissionGate>
        </li>
      </ul>
    </section>
  </div>
</template>
