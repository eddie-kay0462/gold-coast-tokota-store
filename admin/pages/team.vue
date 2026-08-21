<script setup lang="ts">
import { PhClock, PhPlus, PhWarningCircle } from '@phosphor-icons/vue'
import type { AdminUser } from '~/types'
import { ADMIN_ROLE_LABELS } from '~/types'
import { ROLE_CAPABILITIES, ROLE_ORDER } from '~/utils/permissions'

/**
 * Team — Figma node 23:1972, adapted.
 *
 * The kit's Team frame is a sales leaderboard (deals closed, revenue per head).
 * That framing does not transfer: this is a five-person workshop, not a sales
 * floor, and ranking Isaaka against Peter by revenue would be meaningless.
 * What the frame's structure is good for is the roster tile grid and the
 * summary row, so those carry over and the metrics become access-related —
 * who holds what, and whose access is about to lapse.
 */
useHead({ title: 'Team' })

const { useAdminList } = useAdminApi()
const { formatDate, formatRelative, daysUntil } = useFormatters()
const { can } = useAuth()

const { items: team, pending } = useAdminList<AdminUser>('admin-team', '/admin/team')

const byRole = computed(() =>
  ROLE_ORDER.map((role) => ({
    role,
    members: team.value.filter((m) => m.role === role),
  })).filter((g) => g.members.length),
)

const expiring = computed(() =>
  team.value.filter((m) => {
    const d = m.accessExpiresAt ? daysUntil(m.accessExpiresAt) : null
    return d !== null && d <= 14
  }),
)

const accessState = (m: AdminUser) => {
  const d = m.accessExpiresAt ? daysUntil(m.accessExpiresAt) : null
  if (d === null) return null
  if (d < 0) return { tone: 'danger' as const, text: `Lapsed ${formatRelative(m.accessExpiresAt)}` }
  if (d <= 3) return { tone: 'danger' as const, text: `${d} day${d === 1 ? '' : 's'} left` }
  if (d <= 14) return { tone: 'warning' as const, text: `${d} days left` }
  return { tone: 'neutral' as const, text: `Until ${formatDate(m.accessExpiresAt)}` }
}

const target = ref<AdminUser | null>(null)
const extendOpen = ref(false)
function extend(m: AdminUser) { target.value = m; extendOpen.value = true }
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Team" description="Who has access to this dashboard, and at what level.">
      <template #actions>
        <UiPermissionGate capability="team.manage" quiet>
          <UiButton size="sm"><PhPlus :size="16" />Add member</UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <!-- Summary row (Figma 23:1972) -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <UiMetricCard label="Team members" :value="String(team.length)" hint="With dashboard access" />
      <UiMetricCard
        label="Admins" hint="Full or near-full access"
        :value="String(team.filter((m) => m.role === 'super_admin' || m.role === 'admin').length)"
      />
      <UiMetricCard
        label="Staff & interns" hint="Operational and read-only"
        :value="String(team.filter((m) => m.role === 'staff' || m.role === 'intern').length)"
      />
      <UiMetricCard
        label="Access expiring" :value="String(expiring.length)"
        :tone="expiring.length ? 'warning' : 'default'" hint="Within 14 days, or already lapsed"
      />
    </div>

    <div
      v-if="expiring.length"
      class="flex items-start gap-2.5 rounded-lg border border-warning/30 bg-warning-soft px-3.5 py-2.5 text-ui text-warning"
    >
      <PhWarningCircle :size="18" class="mt-px shrink-0" />
      <p>
        {{ expiring.map((m) => m.name).join(', ') }}
        {{ expiring.length === 1 ? 'has' : 'have' }} access ending soon or already lapsed.
      </p>
    </div>

    <div v-if="pending" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
      <div v-for="i in 6" :key="i" class="h-40 animate-pulse rounded-lg bg-bg-sunken" />
    </div>

    <section v-for="group in byRole" :key="group.role" v-else>
      <div class="flex items-baseline gap-2">
        <h2 class="text-meta uppercase tracking-wide text-fg-faint">
          {{ ADMIN_ROLE_LABELS[group.role] }}
        </h2>
        <span class="text-meta text-fg-faint">
          · {{ ROLE_CAPABILITIES[group.role].length }} of
          {{ ROLE_CAPABILITIES.super_admin.length }} permissions
        </span>
      </div>

      <ul class="mt-2 grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        <li v-for="m in group.members" :key="m.id" class="card card-pad flex flex-col">
          <div class="flex items-start gap-3">
            <UiAvatar :name="m.name" :src="m.avatar" :size="44" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-ui font-medium text-fg-strong">{{ m.name }}</p>
              <p class="truncate text-meta text-fg-muted">{{ m.jobTitle }}</p>
              <p class="truncate text-meta text-fg-faint">{{ m.email }}</p>
            </div>
          </div>

          <dl class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-border pt-3 text-meta">
            <div class="flex items-center gap-1.5">
              <dt class="text-fg-faint">Last active</dt>
              <dd class="text-fg-muted">{{ m.lastActiveAt ? formatRelative(m.lastActiveAt) : 'Never' }}</dd>
            </div>
          </dl>

          <!-- Time-boxed access -->
          <div v-if="accessState(m)" class="mt-3 flex flex-wrap items-center gap-2 rounded-lg bg-bg-sunken px-3 py-2.5">
            <PhClock :size="15" class="shrink-0 text-fg-faint" />
            <UiBadge :tone="accessState(m)!.tone" size="sm">{{ accessState(m)!.text }}</UiBadge>
            <span v-if="m.accessExtensions.length" class="text-meta text-fg-faint">
              extended {{ m.accessExtensions.length }}×
            </span>
            <UiButton
              v-if="can('team.extend_access')"
              variant="ghost" size="sm" class="ml-auto"
              @click="extend(m)"
            >Extend</UiButton>
          </div>
          <p v-else class="mt-3 rounded-lg bg-bg-sunken px-3 py-2.5 text-meta text-fg-faint">
            Access does not expire.
          </p>
        </li>
      </ul>
    </section>

    <PeopleExtendAccessModal v-model:open="extendOpen" :member="target" />
  </div>
</template>
