<script setup lang="ts">
import type { AdminRole, AdminUser } from '~/types'
import { ADMIN_ROLE_LABELS } from '~/types'
import { ROLE_DESCRIPTIONS, ROLE_ORDER } from '~/utils/permissions'

/**
 * Add, edit or remove a dashboard account: `POST`, `PUT` and `DELETE
 * /admin/team`. Super Admin only (§18: Users).
 *
 * An intern account needs an end date; no other tier may have one, so the
 * date field only shows for interns. A later change to an intern's date
 * belongs to Extend access, which keeps the history.
 *
 * The API refuses changing your own role, removing yourself, and anything
 * that would leave no Super Admin; its message is shown as-is.
 */
const props = defineProps<{ member: AdminUser | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [] }>()

const { adminFetch } = useAdminApi()
const toast = useToast()
const { user } = useAuth()

const form = reactive({
  name: '', jobTitle: '', email: '', role: 'staff' as AdminRole,
  password: '', passwordConfirmation: '', accessUntil: '',
})
const saving = ref(false)
const deleting = ref(false)
const confirmingDelete = ref(false)
const error = ref<string | null>(null)

const editing = computed(() => !!props.member)
const isSelf = computed(() => !!props.member && props.member.id === user.value?.id)
const roleOptions = ROLE_ORDER.map((r) => ({ value: r, label: ADMIN_ROLE_LABELS[r] }))
const tomorrow = computed(() => new Date(Date.now() + 864e5).toISOString().slice(0, 10))

watch(() => [open.value, props.member] as const, ([isOpen, m]) => {
  if (!isOpen) return
  error.value = null
  confirmingDelete.value = false
  Object.assign(form, {
    name: m?.name ?? '',
    jobTitle: m?.jobTitle ?? '',
    email: m?.email ?? '',
    role: m?.role ?? 'staff',
    password: '',
    passwordConfirmation: '',
    accessUntil: '',
  })
}, { immediate: true })

const needsDate = computed(() => form.role === 'intern' && (!editing.value || props.member?.role !== 'intern'))
const valid = computed(() =>
  !!form.name.trim() && !!form.email.trim()
  && (editing.value || form.password.length > 0)
  && (!needsDate.value || !!form.accessUntil),
)

async function save() {
  if (!valid.value) return
  saving.value = true
  error.value = null
  const body: Record<string, unknown> = {
    name: form.name.trim(),
    job_title: form.jobTitle.trim() || null,
    email: form.email.trim(),
  }
  if (!isSelf.value) body.role = form.role
  if (form.password) {
    body.password = form.password
    body.password_confirmation = form.passwordConfirmation
  }
  if (needsDate.value) body.access_expires_at = new Date(`${form.accessUntil}T23:59:00`).toISOString()
  try {
    if (props.member) {
      await adminFetch(`/admin/team/${props.member.id}`, { method: 'PUT', body })
    } else {
      await adminFetch('/admin/team', { method: 'POST', body })
    }
    toast.success(editing.value ? 'Saved' : 'Member added', editing.value
      ? form.name
      : `${form.name} can sign in with that email and password now. Send them the password yourself.`)
    emit('saved')
    open.value = false
  } catch (err) {
    error.value = failureMessage(err)
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!props.member) return
  deleting.value = true
  error.value = null
  try {
    await adminFetch(`/admin/team/${props.member.id}`, { method: 'DELETE' })
    toast.success('Removed', `${props.member.name} can no longer sign in.`)
    emit('saved')
    open.value = false
  } catch (err) {
    error.value = failureMessage(err, 'Not removed. Try again in a moment.')
    confirmingDelete.value = false
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <UiModal
    v-model:open="open"
    :title="editing ? 'Edit member' : 'Add member'"
    :description="editing ? member!.email : 'Someone who signs in to this dashboard.'"
  >
    <form id="member-form" class="flex flex-col gap-4" @submit.prevent="save">
      <div class="grid gap-4 sm:grid-cols-2">
        <UiField v-model="form.name" label="Name" required autocomplete="off" />
        <UiField v-model="form.jobTitle" label="Job title" hint="Optional. Shown on the team page." />
      </div>
      <UiField v-model="form.email" label="Email" type="email" required autocomplete="off" hint="What they sign in with." />

      <div>
        <UiSelect v-model="form.role" label="Role" :options="roleOptions" :disabled="isSelf" />
        <p class="mt-1 text-meta text-fg-faint">
          {{ isSelf ? 'You can’t change your own role.' : ROLE_DESCRIPTIONS[form.role] }}
        </p>
      </div>

      <div v-if="needsDate">
        <label for="member-until" class="field-label">Access until</label>
        <input id="member-until" v-model="form.accessUntil" type="date" :min="tomorrow" required class="field w-full">
        <p class="mt-1 text-meta text-fg-faint">Intern access ends on this day. Extend it from the team page.</p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <UiField
          v-model="form.password" :label="editing ? 'New password' : 'Password'" type="password"
          :required="!editing" autocomplete="new-password"
          :hint="editing ? 'Leave blank to keep the current one.' : 'At least 8 characters.'"
        />
        <UiField v-model="form.passwordConfirmation" label="Repeat password" type="password" autocomplete="new-password" />
      </div>

      <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ error }}</p>
    </form>

    <template #footer>
      <template v-if="editing && !isSelf">
        <UiButton
          v-if="!confirmingDelete" variant="ghost" size="sm" class="mr-auto text-danger"
          @click="confirmingDelete = true"
        >Remove</UiButton>
        <UiButton
          v-else variant="ghost" size="sm" class="mr-auto text-danger" :loading="deleting"
          @click="remove"
        >Remove {{ member!.name }}?</UiButton>
      </template>
      <UiButton variant="secondary" size="sm" @click="open = false">Cancel</UiButton>
      <UiButton size="sm" type="submit" form="member-form" :loading="saving" :disabled="!valid">
        {{ editing ? 'Save' : 'Add member' }}
      </UiButton>
    </template>
  </UiModal>
</template>
