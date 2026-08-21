<script setup lang="ts">
import { PhArrowSquareOut, PhSignOut, PhCheck } from '@phosphor-icons/vue'
import type { AdminRole } from '~/types'
import { ADMIN_ROLE_LABELS } from '~/types'
import { ROLE_DESCRIPTIONS, ROLE_ORDER } from '~/utils/permissions'
import { adminUsers } from '~/fixtures'

/**
 * Account profile modal, opened from the sidebar user tile.
 *
 * The "View as" switcher is the part worth explaining. Permission gating is
 * hard to review when there is no working login and no second account: you
 * cannot tell whether Staff is correctly blocked from refunds without being
 * Staff. This lets a reviewer step through all four tiers and watch the
 * navigation, buttons and pages change.
 *
 * It only ever narrows the UI. It cannot widen server access, because the
 * server is the real boundary (EnsureAdminRole against the `admin` guard) and
 * this app never tells it anything about the selected role.
 */
const open = defineModel<boolean>('open', { default: false })

const { user, role, isImpersonating, setViewAsRole, accessExpiresAt, logout } = useAuth()
const { formatDate, formatRelative } = useFormatters()

const actualRole = computed(() => user.value?.role ?? 'intern')

/** Whether a previewed intern should be shown as lapsed or still in window. */
const previewLapsed = ref(false)

/**
 * A representative expiry for the previewed role, taken from the fixture
 * roster: `intern` is time-boxed, everything else is not. Without this the
 * preview only changed capabilities, so the countdown banner and the lapsed
 * read-only state were unreachable in the app.
 */
function expiryFor(role: AdminRole): string | null {
  if (role !== 'intern') return null
  const interns = adminUsers.filter((u) => u.role === 'intern' && u.accessExpiresAt)
  const lapsed = interns.find((u) => new Date(u.accessExpiresAt!) < new Date('2026-08-21T14:30:00Z'))
  const active = interns.find((u) => new Date(u.accessExpiresAt!) >= new Date('2026-08-21T14:30:00Z'))
  return (previewLapsed.value ? lapsed : active)?.accessExpiresAt ?? null
}

function choose(next: AdminRole) {
  if (next === actualRole.value) setViewAsRole(null)
  else setViewAsRole(next, expiryFor(next))
}

// Toggling lapsed while already previewing an intern re-applies immediately.
watch(previewLapsed, () => {
  if (role.value === 'intern' && role.value !== actualRole.value) {
    setViewAsRole('intern', expiryFor('intern'))
  }
})
</script>

<template>
  <UiModal v-model:open="open" title="Your account" size="md">
    <div class="flex flex-col gap-6">
      <!-- Identity -->
      <div class="flex items-center gap-3">
        <UiAvatar :name="user?.name ?? ''" :src="user?.avatar" :size="56" />
        <div class="min-w-0">
          <p class="truncate text-section font-medium text-fg-strong">{{ user?.name }}</p>
          <p class="truncate text-ui text-fg-muted">{{ user?.jobTitle }}</p>
          <p class="truncate text-meta text-fg-faint">{{ user?.email }}</p>
        </div>
      </div>

      <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-t border-border pt-4 text-ui">
        <div>
          <dt class="text-meta text-fg-faint">Role</dt>
          <dd class="mt-0.5 text-fg-strong">{{ ADMIN_ROLE_LABELS[actualRole] }}</dd>
        </div>
        <div v-if="accessExpiresAt">
          <dt class="text-meta text-fg-faint">Access ends</dt>
          <dd class="mt-0.5 text-fg-strong">{{ formatDate(accessExpiresAt) }}</dd>
        </div>
        <div>
          <dt class="text-meta text-fg-faint">Session</dt>
          <dd class="mt-0.5 text-fg-strong">Demo — not authenticated</dd>
        </div>
      </dl>

      <!-- Role preview -->
      <div class="border-t border-border pt-4">
        <p class="text-ui font-medium text-fg-strong">View as</p>
        <p class="mt-1 text-meta text-fg-muted">
          Preview the dashboard as another role. This only changes what this browser shows —
          access is enforced by the API, not here.
        </p>

        <div class="mt-3 flex flex-col gap-1.5">
          <button
            v-for="r in ROLE_ORDER" :key="r"
            type="button"
            class="flex items-start gap-3 rounded-lg border p-3 text-left transition-colors"
            :class="role === r
              ? 'border-accent bg-accent-soft'
              : 'border-border hover:bg-bg-sunken'"
            @click="choose(r)"
          >
            <span
              class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-pill border"
              :class="role === r ? 'border-accent bg-accent text-accent-fg' : 'border-border-strong'"
            >
              <PhCheck v-if="role === r" :size="10" weight="bold" />
            </span>
            <span class="min-w-0">
              <span class="flex items-center gap-2 text-ui font-medium text-fg-strong">
                {{ ADMIN_ROLE_LABELS[r] }}
                <UiBadge v-if="r === actualRole" tone="outline" size="sm">Your role</UiBadge>
              </span>
              <span class="mt-0.5 block text-meta text-fg-muted">{{ ROLE_DESCRIPTIONS[r] }}</span>
            </span>
          </button>
        </div>

        <label
          v-if="role === 'intern'"
          class="mt-2.5 flex cursor-pointer items-start gap-2.5"
        >
          <input
            v-model="previewLapsed" type="checkbox"
            class="mt-0.5 size-4 rounded-sm border-border-strong text-accent focus:ring-accent"
          >
          <span class="min-w-0">
            <span class="block text-ui text-fg-strong">Preview an expired intern</span>
            <span class="block text-meta text-fg-muted">
              Shows the lapsed, read-only state an intern falls into once their access window
              closes.
            </span>
          </span>
        </label>

        <button
          v-if="isImpersonating"
          type="button"
          class="mt-2.5 text-meta text-accent-text underline-offset-4 hover:underline"
          @click="previewLapsed = false; setViewAsRole(null)"
        >
          Return to {{ ADMIN_ROLE_LABELS[actualRole] }}
        </button>
      </div>
    </div>

    <template #footer>
      <UiButton variant="secondary" size="sm" to="/account" @click="open = false">
        <PhArrowSquareOut :size="16" />
        Account settings
      </UiButton>
      <UiButton variant="ghost" size="sm" @click="logout()">
        <PhSignOut :size="16" />
        Sign out
      </UiButton>
    </template>
  </UiModal>
</template>
