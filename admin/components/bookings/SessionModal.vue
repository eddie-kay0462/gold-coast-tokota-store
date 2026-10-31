<script setup lang="ts">
import type { WorkshopSession, WorkshopType } from '~/types'

/**
 * Add, edit or delete a workshop session: `POST`, `PUT` and `DELETE
 * /admin/workshop-sessions`.
 *
 * A session is one dated run of a workshop type, with its own capacity, which
 * is what the storefront offers seats on. Picking a type fills in its usual
 * capacity and suggests its usual times, but both can be changed for the day.
 *
 * The API refuses to cut capacity below the seats already booked, or to
 * delete a session anyone is booked on; its message says how many, and is
 * shown as-is.
 *
 * A title makes it a themed run ("Halloween Sip & Paint"): it goes by that
 * name on the booking page and in Bookings, and stays under its workshop.
 */
const props = defineProps<{
  session: WorkshopSession | null
  types: WorkshopType[]
  /** Pre-fills the date when adding from a calendar day. */
  date?: string | null
}>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: []; deleted: [] }>()

const { adminFetch } = useAdminApi()
const toast = useToast()

const form = reactive({ typeId: '', title: '', description: '', date: '', slot: '', capacity: '', price: '', notes: '' })
const saving = ref(false)
const deleting = ref(false)
const confirmingDelete = ref(false)
const error = ref<string | null>(null)

const today = computed(() => new Date().toISOString().slice(0, 10))
const editing = computed(() => !!props.session)

const typeOptions = computed(() =>
  props.types
    .filter((t) => t.isActive || String(t.id) === form.typeId)
    .map((t) => ({ value: String(t.id), label: t.name })),
)
const selectedType = computed(() => props.types.find((t) => String(t.id) === form.typeId) ?? null)
const slotSuggestions = computed(() =>
  (selectedType.value?.slotLabel ?? '').split(' / ').map((s) => s.trim()).filter(Boolean),
)

watch(() => [open.value, props.session] as const, ([isOpen, s]) => {
  if (!isOpen) return
  error.value = null
  confirmingDelete.value = false
  if (s) {
    Object.assign(form, {
      typeId: String(s.workshopTypeId),
      title: s.title ?? '',
      description: s.description ?? '',
      date: s.scheduledDate,
      slot: s.scheduledSlot,
      capacity: String(s.capacity),
      price: s.priceGhs ? (s.priceGhs / 100).toFixed(2) : '',
      notes: s.locationNotes ?? '',
    })
  } else {
    const first = props.types.find((t) => t.isActive && !t.requiresAppointment) ?? props.types[0]
    Object.assign(form, {
      typeId: first ? String(first.id) : '',
      title: '',
      description: '',
      date: props.date ?? '',
      slot: '',
      capacity: '',
      price: '',
      notes: '',
    })
  }
}, { immediate: true })

// A new session starts from the type's usual capacity and first time slot.
watch(() => form.typeId, () => {
  if (editing.value || !selectedType.value) return
  form.capacity = String(selectedType.value.capacity)
  form.slot = slotSuggestions.value[0] ?? ''
}, { immediate: true })

// Blank follows the event's price.
const priceMinor = computed(() => (String(form.price).trim() === '' ? null : Math.round(Number(form.price) * 100)))
const priceValid = computed(() => priceMinor.value === null || (Number.isFinite(priceMinor.value) && priceMinor.value >= 100))
const eventPrice = computed(() => selectedType.value?.priceGhs ? `GH₵${(selectedType.value.priceGhs / 100).toFixed(2)}` : null)
const valid = computed(() =>
  priceValid.value && !!form.typeId && !!form.date && !!form.slot.trim()
  && Number.isInteger(Number(form.capacity)) && Number(form.capacity) >= 1,
)

async function save() {
  if (!valid.value) return
  saving.value = true
  error.value = null
  const body = {
    workshop_type_id: Number(form.typeId),
    title: form.title.trim() || null,
    description: form.description.trim() || null,
    scheduled_date: form.date,
    scheduled_slot: form.slot.trim(),
    capacity: Number(form.capacity),
    price_ghs: priceMinor.value,
    location_notes: form.notes.trim() || null,
  }
  try {
    if (props.session) {
      await adminFetch(`/admin/workshop-sessions/${props.session.id}`, { method: 'PUT', body })
    } else {
      await adminFetch('/admin/workshop-sessions', { method: 'POST', body })
    }
    toast.success(editing.value ? 'Session updated' : 'Session added', 'It shows on the booking page now.')
    emit('saved')
    open.value = false
  } catch (err) {
    error.value = failureMessage(err)
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!props.session) return
  deleting.value = true
  error.value = null
  try {
    await adminFetch(`/admin/workshop-sessions/${props.session.id}`, { method: 'DELETE' })
    toast.success('Session deleted')
    emit('deleted')
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
    :title="editing ? 'Edit session' : 'New session'"
    :description="editing ? `${session!.eventName ?? session!.workshopTypeName} · ${session!.occupiedSeats} booked` : 'A dated run of a workshop, with its own seats.'"
  >
    <form id="session-form" class="flex flex-col gap-4" @submit.prevent="save">
      <UiSelect v-model="form.typeId" label="Workshop" :options="typeOptions" />
      <p v-if="selectedType?.requiresAppointment" class="-mt-2 text-meta text-fg-faint">
        This one usually runs by appointment. Add a session only for a date you’ve agreed.
      </p>

      <UiField
        v-model="form.title" label="Theme or title" maxlength="120"
        :placeholder="selectedType ? `e.g. Halloween ${selectedType.name}` : 'e.g. Halloween Sip & Paint'"
        :hint="form.title.trim() ? 'Shown instead of the workshop’s name for this date.' : `Optional. Blank: it’s listed as ${selectedType?.name ?? 'the workshop'}.`"
      />
      <UiField
        v-if="form.title.trim()" v-model="form.description" label="About this one" type="textarea" :rows="2"
        hint="Optional. What’s different this time, for the booking page."
      />

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label for="session-date" class="field-label">Date</label>
          <input id="session-date" v-model="form.date" type="date" :min="today" required class="field w-full">
        </div>
        <UiField
          v-model="form.capacity" label="Seats" type="number" required
          :hint="selectedType ? `Usually ${selectedType.capacity}.` : undefined"
        />
      </div>

      <UiField
        v-model="form.slot" label="Time" required list="session-slots"
        placeholder="e.g. 10:00 AM to 1:00 PM"
      />
      <datalist id="session-slots">
        <option v-for="s in slotSuggestions" :key="s" :value="s" />
      </datalist>

      <UiField
        v-model="form.price" label="Price per person for this date (GHS)" type="number"
        :hint="!priceValid ? 'At least GH₵1, or blank.' : eventPrice ? `Blank: the event’s price, ${eventPrice}.` : 'Blank: free, like the event.'"
      />

      <UiField v-model="form.notes" label="Location notes" type="textarea" :rows="2" hint="Optional. Shown with the booking." />

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
        >Delete this session?</UiButton>
      </template>
      <UiButton variant="secondary" size="sm" @click="open = false">Cancel</UiButton>
      <UiButton size="sm" type="submit" form="session-form" :loading="saving" :disabled="!valid">
        {{ editing ? 'Save' : 'Add session' }}
      </UiButton>
    </template>
  </UiModal>
</template>
