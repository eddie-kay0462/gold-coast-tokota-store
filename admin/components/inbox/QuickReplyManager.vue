<script setup lang="ts">
import { PhPencilSimple, PhPlus, PhTrash } from '@phosphor-icons/vue'
import type { InboxAutomation, QuickReply } from '~/types'

/**
 * The team's saved replies. Everyone at the inbox uses them (as chips above
 * the reply box, or by typing "/" and a shortcut); writing them needs
 * `inbox.templates`. Slots like {order.reference} are filled by the server
 * from the chat each time, from the same figures the website shows.
 */
const props = defineProps<{ replies: QuickReply[]; slots: InboxAutomation['slots'] }>()
const emit = defineEmits<{ (e: 'changed'): void }>()

const { adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()

const editing = ref<Partial<QuickReply> | null>(null)
const saving = ref(false)
const error = ref<string | null>(null)

function edit(reply?: QuickReply) {
  editing.value = reply ? { ...reply } : { title: '', shortcut: '', body: '' }
  error.value = null
}

function addSlot(slot: string) {
  if (editing.value) editing.value.body = `${editing.value.body ?? ''}${slot}`
}

async function save() {
  if (!editing.value) return
  saving.value = true
  error.value = null
  const { id, title, shortcut, body } = editing.value
  try {
    await adminFetch(id ? `/admin/inbox/quick-replies/${id}` : '/admin/inbox/quick-replies', {
      method: id ? 'PUT' : 'POST',
      body: { title, shortcut: shortcut || null, body },
    })
    toast.success(id ? 'Quick reply saved' : 'Quick reply added')
    editing.value = null
    emit('changed')
  } catch (err) {
    const e = err as { data?: { message?: string; errors?: Record<string, string[]> } }
    error.value = Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? 'Not saved. Try again in a moment.'
  } finally {
    saving.value = false
  }
}

async function remove(reply: QuickReply) {
  if (!window.confirm(`Delete the quick reply “${reply.title}”?`)) return
  try {
    await adminFetch(`/admin/inbox/quick-replies/${reply.id}`, { method: 'DELETE' })
    emit('changed')
  } catch {
    toast.error('Not deleted', 'Try again in a moment.')
  }
}

const canEdit = computed(() => can('inbox.templates'))
</script>

<template>
  <div class="admin-stack">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <p class="max-w-prose text-ui text-fg-muted">
        Saved replies for the questions that come up every day. Use them from the chips above the
        reply box, or type <span class="font-mono">/</span> and a shortcut. Words in braces are
        filled in from the chat, so an order number or a delivery time is always the real one.
      </p>
      <UiButton v-if="canEdit" size="sm" @click="edit()"><PhPlus :size="16" /> New quick reply</UiButton>
    </div>

    <UiEmptyState v-if="!props.replies.length" title="No quick replies yet" description="Add the answers your team types most often." />

    <ul v-else class="grid gap-3 lg:grid-cols-2">
      <li v-for="r in props.replies" :key="r.id" class="card card-pad">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-ui font-medium text-fg-strong">{{ r.title }}</p>
            <p v-if="r.shortcut" class="font-mono text-meta text-fg-faint">/{{ r.shortcut }}</p>
          </div>
          <div v-if="canEdit" class="flex shrink-0 gap-1">
            <button type="button" class="toolbar-btn" :aria-label="`Edit ${r.title}`" @click="edit(r)"><PhPencilSimple :size="16" /></button>
            <button type="button" class="toolbar-btn" :aria-label="`Delete ${r.title}`" @click="remove(r)"><PhTrash :size="16" /></button>
          </div>
        </div>
        <p class="mt-2.5 whitespace-pre-line rounded-lg bg-bg-sunken px-3 py-2.5 text-ui leading-relaxed text-fg">{{ r.body }}</p>
      </li>
    </ul>

    <UiModal :open="!!editing" size="md" :title="editing?.id ? 'Edit quick reply' : 'New quick reply'" @update:open="(v: boolean) => { if (!v) editing = null }">
      <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
        <div class="grid gap-3 sm:grid-cols-2">
          <UiField v-model="editing.title" label="Title" hint="Shown on the chip, e.g. “Delivery times”." />
          <UiField v-model="editing.shortcut" label="Shortcut (optional)" hint="Letters, numbers and dashes. Typed after /." />
        </div>
        <div>
          <label class="field-label" for="qr-body">Reply</label>
          <textarea id="qr-body" v-model="editing.body" rows="5" class="field w-full resize-y" />
        </div>
        <div>
          <p class="field-label">Insert a value from the chat</p>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="s in slots" :key="s.slot" type="button" :title="s.description"
              class="rounded-pill border border-border px-2.5 py-1 font-mono text-micro text-fg-muted hover:bg-bg-sunken hover:text-fg-strong"
              @click="addSlot(s.slot)"
            >{{ s.slot }}</button>
          </div>
        </div>
        <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ error }}</p>
      </form>
      <template #footer>
        <UiButton variant="secondary" size="sm" @click="editing = null">Cancel</UiButton>
        <UiButton size="sm" :loading="saving" :disabled="!editing?.title?.trim() || !editing?.body?.trim()" @click="save">Save</UiButton>
      </template>
    </UiModal>
  </div>
</template>
