<script setup lang="ts">
import type { WorkshopType } from '~/types'

/**
 * Add or edit an experience on the booking page: `POST`, `PUT` and `DELETE
 * /admin/workshop-types`. A new one is a new kind of event; a themed run of an
 * existing one ("Halloween Sip & Paint") is a session with its own title
 * instead, so its bookings stay with the experience.
 *
 * The schedule fields are what customers read on the booking page. The actual
 * dates and seats are set per session. Only an experience that never had a
 * session can be deleted; any other is switched off (the API says so).
 */
const props = defineProps<{ type: WorkshopType | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [] }>()

const { adminFetch } = useAdminApi()
const toast = useToast()

const form = reactive({
  name: '', description: '', daysLabel: '', slotLabel: '', durationLabel: '', capacity: '',
  requiresAppointment: false, isActive: true,
})
const saving = ref(false)
const deleting = ref(false)
const confirmingDelete = ref(false)
const error = ref<string | null>(null)
const editing = computed(() => !!props.type)

watch(() => [open.value, props.type] as const, ([isOpen, t]) => {
  if (!isOpen) return
  error.value = null
  confirmingDelete.value = false
  Object.assign(form, {
    name: t?.name ?? '',
    description: t?.description ?? '',
    daysLabel: t?.daysLabel ?? '',
    slotLabel: t?.slotLabel ?? '',
    durationLabel: t?.durationLabel ?? '',
    capacity: t ? String(t.capacity) : '',
    requiresAppointment: t?.requiresAppointment ?? false,
    isActive: t?.isActive ?? true,
  })
}, { immediate: true })

const valid = computed(() =>
  !!form.name.trim() && !!form.daysLabel.trim() && !!form.slotLabel.trim() && !!form.durationLabel.trim()
  && Number.isInteger(Number(form.capacity)) && Number(form.capacity) >= 1,
)

async function save() {
  if (!valid.value) return
  saving.value = true
  error.value = null
  const body = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    days_label: form.daysLabel.trim(),
    slot_label: form.slotLabel.trim(),
    duration_label: form.durationLabel.trim(),
    capacity: Number(form.capacity),
    requires_appointment: form.requiresAppointment,
    is_active: form.isActive,
  }
  try {
    if (props.type) await adminFetch(`/admin/workshop-types/${props.type.id}`, { method: 'PUT', body })
    else await adminFetch('/admin/workshop-types', { method: 'POST', body })
    toast.success(editing.value ? 'Event saved' : 'Event added', form.isActive
      ? 'Add a session so it can be booked.'
      : 'It’s switched off, so the booking page doesn’t show it.')
    emit('saved')
    open.value = false
  } catch (err) {
    error.value = failureMessage(err)
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!props.type) return
  deleting.value = true
  error.value = null
  try {
    await adminFetch(`/admin/workshop-types/${props.type.id}`, { method: 'DELETE' })
    toast.success('Event deleted', props.type.name)
    emit('saved')
    open.value = false
  } catch (err) {
    error.value = failureMessage(err, 'Not deleted. Try again in a moment.')
    confirmingDelete.value = false
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <UiModal
    v-model:open="open"
    :title="editing ? 'Edit event' : 'New event'"
    :description="editing ? type!.name : 'A new experience on the booking page. Dates and seats are added as sessions.'"
  >
    <form id="event-form" class="flex flex-col gap-4" @submit.prevent="save">
      <UiField v-model="form.name" label="Name" required placeholder="e.g. Kids Sandal Studio" />
      <UiField v-model="form.description" label="Description" type="textarea" :rows="3" hint="What customers read on the booking page." />
      <div class="grid gap-4 sm:grid-cols-2">
        <UiField v-model="form.daysLabel" label="When it runs" required placeholder="e.g. Every Saturday" />
        <UiField v-model="form.slotLabel" label="Usual time" required placeholder="e.g. 10:00 AM to 1:00 PM" />
        <UiField v-model="form.durationLabel" label="Length" required placeholder="e.g. 3 hours" />
        <UiField v-model="form.capacity" label="Usual seats" type="number" required hint="Each session can change it." />
      </div>
      <label class="flex items-center gap-2 text-ui text-fg">
        <input v-model="form.requiresAppointment" type="checkbox" class="size-4">
        By appointment: no standing dates
      </label>
      <label class="flex items-center gap-2 text-ui text-fg">
        <input v-model="form.isActive" type="checkbox" class="size-4">
        On the booking page
      </label>
      <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ error }}</p>
    </form>

    <template #footer>
      <template v-if="editing">
        <UiButton
          v-if="!confirmingDelete" variant="ghost" size="sm" class="mr-auto text-danger"
          @click="confirmingDelete = true"
        >Delete</UiButton>
        <UiButton
          v-else variant="ghost" size="sm" class="mr-auto text-danger" :loading="deleting"
          @click="remove"
        >Delete {{ type!.name }}?</UiButton>
      </template>
      <UiButton variant="secondary" size="sm" @click="open = false">Cancel</UiButton>
      <UiButton size="sm" type="submit" form="event-form" :loading="saving" :disabled="!valid">
        {{ editing ? 'Save' : 'Add event' }}
      </UiButton>
    </template>
  </UiModal>
</template>
