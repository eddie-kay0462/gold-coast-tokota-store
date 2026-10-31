<script setup lang="ts">
import { PhClock, PhSignOut, PhWarningCircle } from '@phosphor-icons/vue'
import { ADMIN_ROLE_LABELS } from '~/types'
import { ROLE_CAPABILITIES, type Capability } from '~/utils/permissions'
import { humanise } from '~/utils/formatters'

/**
 * Account settings: the person's own profile, preferences and session.
 *
 * Separate from the profile modal, which is a quick glance and a role switch.
 * This is where you change things about yourself.
 */
useHead({ title: 'Account' })

const { user, role, roleDescription, accessExpiresAt, accessDaysRemaining, hasLapsed, logout, restoreSession } = useAuth()
const { adminFetch } = useAdminApi()
const toast = useToast()
const { preference, setPreference, resolved } = useTheme()
const { formatDate } = useFormatters()

const form = reactive({ name: '', email: '', jobTitle: '' })
watchEffect(() => {
  if (!user.value) return
  Object.assign(form, {
    name: user.value.name, email: user.value.email, jobTitle: user.value.jobTitle,
  })
})

// `PUT /admin/me`. A new email needs your current password, as does a new
// password; the API checks it.
const current = reactive({ forProfile: '', forPassword: '' })
const passwords = reactive({ next: '', confirm: '' })
const saving = ref<'profile' | 'password' | null>(null)
const profileError = ref<string | null>(null)
const passwordError = ref<string | null>(null)
const emailChanged = computed(() => !!user.value && form.email.trim() !== user.value.email)

async function saveMe(section: 'profile' | 'password') {
  const error = section === 'profile' ? profileError : passwordError
  error.value = null
  const body = section === 'profile'
    ? {
        name: form.name.trim(), job_title: form.jobTitle.trim() || null, email: form.email.trim(),
        ...(emailChanged.value ? { current_password: current.forProfile } : {}),
      }
    : { password: passwords.next, password_confirmation: passwords.confirm, current_password: current.forPassword }
  saving.value = section
  try {
    await adminFetch('/admin/me', { method: 'PUT', body })
    await restoreSession()
    if (section === 'password') Object.assign(passwords, { next: '', confirm: '' })
    current.forProfile = ''
    current.forPassword = ''
    toast.success(section === 'profile' ? 'Profile saved' : 'Password changed')
  } catch (err) {
    error.value = failureMessage(err)
  } finally {
    saving.value = null
  }
}

const themeOptions = [
  { value: 'light', label: 'Light' },
  { value: 'dark', label: 'Dark' },
  { value: 'system', label: 'Match my system' },
]

/** Grouped so the list reads as areas of the app rather than 39 flat strings. */
const grantedByArea = computed(() => {
  const map = new Map<string, Capability[]>()
  for (const c of ROLE_CAPABILITIES[role.value]) {
    const [area] = c.split('.')
    const list = map.get(area!) ?? []
    list.push(c)
    map.set(area!, list)
  }
  return [...map.entries()].map(([area, caps]) => ({ area: humanise(area), caps }))
})
</script>

<template>
  <div class="admin-stack max-w-3xl">
    <UiPageHeader title="Account" description="Your profile, preferences and session." />

    <div
      v-if="hasLapsed"
      class="flex items-start gap-2.5 rounded-lg border border-danger/30 bg-danger-soft px-3.5 py-3 text-ui text-danger"
    >
      <PhWarningCircle :size="18" class="mt-px shrink-0" />
      <p>
        Your access period ended on {{ formatDate(accessExpiresAt) }}. The dashboard is read-only
        until an Admin extends it.
      </p>
    </div>

    <SettingsSection title="Profile">
      <div class="flex flex-wrap items-center gap-4">
        <UiAvatar :name="user?.name ?? ''" :src="user?.avatar" :size="64" />
        <div class="min-w-0">
          <p class="text-section text-fg-strong">{{ user?.name }}</p>
          <p class="text-ui text-fg-muted">{{ user?.jobTitle }}</p>
        </div>
      </div>

      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <UiField v-model="form.name" label="Full name" />
        <UiField v-model="form.jobTitle" label="Job title" />
        <UiField
          v-model="form.email" label="Email address" type="email" class="sm:col-span-2"
          hint="Also the address you sign in with."
        />
        <UiField
          v-if="emailChanged" v-model="current.forProfile" label="Current password" type="password"
          autocomplete="current-password" class="sm:col-span-2" hint="Needed to change your email."
        />
      </div>
      <p v-if="profileError" class="mt-3 text-meta text-danger" role="alert">{{ profileError }}</p>

      <template #footer>
        <UiButton size="sm" :loading="saving === 'profile'" :disabled="!form.name.trim() || !form.email.trim()" @click="saveMe('profile')">
          Save changes
        </UiButton>
      </template>
    </SettingsSection>

    <SettingsSection title="Password">
      <div class="grid gap-4 sm:grid-cols-2">
        <UiField v-model="current.forPassword" label="Current password" type="password" autocomplete="current-password" class="sm:col-span-2" />
        <UiField v-model="passwords.next" label="New password" type="password" autocomplete="new-password" hint="At least 8 characters." />
        <UiField v-model="passwords.confirm" label="Repeat new password" type="password" autocomplete="new-password" />
      </div>
      <p v-if="passwordError" class="mt-3 text-meta text-danger" role="alert">{{ passwordError }}</p>
      <template #footer>
        <UiButton
          size="sm" :loading="saving === 'password'"
          :disabled="!current.forPassword || !passwords.next || passwords.next !== passwords.confirm"
          @click="saveMe('password')"
        >Change password</UiButton>
      </template>
    </SettingsSection>

    <SettingsSection
      title="Appearance"
      :description="`Currently rendering in ${resolved} mode.`"
    >
      <div class="flex flex-wrap gap-2">
        <button
          v-for="o in themeOptions" :key="o.value"
          type="button"
          class="rounded-lg border px-4 py-2.5 text-ui transition-colors"
          :class="preference === o.value
            ? 'border-accent bg-accent-soft font-medium text-accent-text'
            : 'border-border text-fg-muted hover:bg-bg-sunken'"
          @click="setPreference(o.value as 'light' | 'dark' | 'system')"
        >{{ o.label }}</button>
      </div>
      <p class="mt-3 text-meta text-fg-muted">
        “Match my system” follows your operating system live, so the dashboard dims when your
        machine does.
      </p>
    </SettingsSection>

    <SettingsSection
      title="Access"
      :description="`You are signed in as ${ADMIN_ROLE_LABELS[role]}.`"
    >
      <p class="text-ui text-fg-muted">{{ roleDescription }}</p>

      <div v-if="accessExpiresAt" class="mt-4 flex flex-wrap items-center gap-3 rounded-lg bg-bg-sunken px-4 py-3">
        <PhClock :size="18" class="shrink-0 text-fg-faint" />
        <p class="min-w-0 flex-1 text-ui text-fg">
          <template v-if="hasLapsed">Access ended on {{ formatDate(accessExpiresAt) }}.</template>
          <template v-else>
            Access runs until {{ formatDate(accessExpiresAt) }}
            ({{ accessDaysRemaining }} day{{ accessDaysRemaining === 1 ? '' : 's' }} left).
          </template>
        </p>
      </div>

      <div class="mt-4 border-t border-border pt-4">
        <p class="field-label">What you can do</p>
        <div class="flex flex-col gap-2.5">
          <div v-for="g in grantedByArea" :key="g.area">
            <p class="text-meta uppercase tracking-wide text-fg-faint">{{ g.area }}</p>
            <ul class="mt-1 flex flex-wrap gap-1.5">
              <li v-for="c in g.caps" :key="c">
                <UiBadge tone="outline" size="sm">{{ humanise(c.split('.')[1] ?? c) }}</UiBadge>
              </li>
            </ul>
          </div>
        </div>
        <p class="mt-3 text-meta text-fg-muted">
          Need something that isn't here? Ask an Admin, or see
          <NuxtLink to="/settings/roles" class="text-accent-text underline underline-offset-4">
            Roles &amp; access
          </NuxtLink>
          for what each tier covers.
        </p>
      </div>
    </SettingsSection>

    <SettingsSection title="Session">
      <p class="text-ui text-fg-muted">
        You aren’t actually signed in. Sign-in hasn’t been built yet, so the dashboard is
        open and this is a demo session.
      </p>
      <template #footer>
        <UiButton variant="secondary" size="sm" @click="logout()">
          <PhSignOut :size="16" />
          Sign out
        </UiButton>
      </template>
    </SettingsSection>
  </div>
</template>
