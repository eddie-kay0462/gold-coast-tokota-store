<script setup lang="ts">
import {
  PhFileText, PhImage, PhPaperPlaneTilt, PhDotsThreeVertical,
  PhCheck, PhChecks, PhClock, PhWarningCircle, PhLink, PhReceipt, PhCalendarPlus,
  PhHammer, PhArchive, PhArrowCounterClockwise, PhUserCircle, PhDeviceMobile, PhRobot,
  PhTag, PhProhibit, PhSidebarSimple,
} from '@phosphor-icons/vue'
import type { ChatMessage, ChatThread, MessageTemplate, ThreadTopic } from '~/types'

/**
 * Thread view and composer, Figma node 29:8158 right pane.
 *
 * What the operator must be able to trust from this pane alone:
 *   - whether each reply reached the customer (ticks, "not delivered", the
 *     reason a send failed);
 *   - who said it (the customer, a team member, the brand phone, automation);
 *   - whether a free-form reply is still allowed (WhatsApp's 24-hour window),
 *     and if not, a template picker instead of a box that would be refused.
 */
const props = defineProps<{
  thread: ChatThread | null
  messages: ChatMessage[]
  templates?: MessageTemplate[]
  sending?: boolean
  /** Live API data: times are compared with the real clock, not the fixture one. */
  live?: boolean
}>()

const emit = defineEmits<{
  (e: 'send', body: string): void
  (e: 'send-template', payload: { templateId: string; parameters: string[] }): void
  (e: 'send-draft', payload: { id: string; body: string }): void
  (e: 'discard-draft', id: string): void
  (e: 'archive' | 'reopen' | 'assign-to-me' | 'unassign'): void
  (e: 'link-order', reference: string | null): void
  (e: 'set-topic', topic: ThreadTopic): void
  (e: 'toggle-panel'): void
}>()

const { formatDate, formatTime, now: fixtureNow } = useFormatters()

/**
 * The 24-hour window is a live fact, so live threads use the real clock.
 * `useFormatters().now` switches to the fixed fixture clock as soon as any
 * screen anywhere has fallen back to fixtures (the activity feed still does),
 * which would show a live window as open or closed against the wrong time.
 */
const wallClock = ref(new Date())
let clockTimer: ReturnType<typeof setInterval> | null = null
onMounted(() => { clockTimer = setInterval(() => { wallClock.value = new Date() }, 30_000) })
onBeforeUnmount(() => { if (clockTimer) clearInterval(clockTimer) })
const now = computed(() => (props.live ? wallClock.value : fixtureNow.value))
const { can, user } = useAuth()

const canReply = computed(() => can('inbox.reply'))
const canDraft = computed(() => can('inbox.draft'))

// --- conversation actions -----------------------------------------------

const linkOpen = ref(false)
const linkReference = ref('')

function openLink() {
  linkReference.value = props.thread?.linkedOrderReference ?? ''
  linkOpen.value = true
}

function saveLink() {
  emit('link-order', linkReference.value.trim() || null)
  linkOpen.value = false
}

const topics: { value: ThreadTopic; label: string }[] = [
  { value: 'order', label: 'Order' },
  { value: 'booking', label: 'Workshop booking' },
  { value: 'diy_order', label: 'DIY / custom order' },
  { value: 'returns', label: 'Returns & exchanges' },
  { value: 'wholesale', label: 'Bulk & corporate' },
  { value: 'general', label: 'General' },
]

function createBooking() {
  navigateTo({ path: '/bookings', query: { from: props.thread?.id } })
}

function convertToDiy() {
  navigateTo({ path: '/bookings', query: { from: props.thread?.id, type: 'diy_order' } })
}

// --- messages -------------------------------------------------------------

const scroller = ref<HTMLElement | null>(null)

/**
 * Group by day, then by consecutive run of the same direction. The frame shows
 * one avatar per run beside the first bubble, not one per message; repeating
 * it turns a three-line reply into a column of identical circles.
 */
interface Run { direction: ChatMessage['direction']; items: ChatMessage[] }

const grouped = computed(() => {
  const days: { day: string; runs: Run[] }[] = []
  for (const m of props.messages) {
    const day = m.sentAt.slice(0, 10)
    let dayEntry = days[days.length - 1]
    if (dayEntry?.day !== day) {
      dayEntry = { day, runs: [] }
      days.push(dayEntry)
    }
    const lastRun = dayEntry.runs[dayEntry.runs.length - 1]
    if (lastRun && lastRun.direction === m.direction) lastRun.items.push(m)
    else dayEntry.runs.push({ direction: m.direction, items: [m] })
  }
  return days
})

const sourceLabel: Partial<Record<NonNullable<ChatMessage['source']>, { label: string; icon: unknown }>> = {
  phone_app: { label: 'Sent from the brand phone', icon: PhDeviceMobile },
  automation: { label: 'Automatic', icon: PhRobot },
  history: { label: 'Imported from the phone', icon: PhDeviceMobile },
}

function statusIcon(m: ChatMessage) {
  return {
    queued: PhClock,
    sent: PhCheck,
    delivered: PhChecks,
    read: PhChecks,
    failed: PhWarningCircle,
  }[m.status as 'queued'] ?? null
}

function isDraft(m: ChatMessage) {
  return m.status === 'draft'
}

const editingDraft = ref<string | null>(null)
const draftEdit = ref('')

function startEditing(m: ChatMessage) {
  editingDraft.value = m.id
  draftEdit.value = m.body
}

function sendDraft(m: ChatMessage) {
  emit('send-draft', { id: m.id, body: editingDraft.value === m.id ? draftEdit.value : m.body })
  editingDraft.value = null
}

function canDiscard(m: ChatMessage) {
  return canReply.value || m.draftedByName === user.value?.name
}

async function scrollToEnd() {
  await nextTick()
  if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight
}
watch(() => props.thread?.id, scrollToEnd, { immediate: true })
watch(() => props.messages.length, scrollToEnd)

// --- the 24-hour window ---------------------------------------------------

/**
 * WhatsApp only allows a free-form reply within 24 hours of the customer's
 * last message; after that, only an approved template. The API refuses
 * anything else, so the composer changes shape instead of letting someone
 * type a reply that cannot be sent.
 */
const windowClosesAt = computed(() => (props.thread?.sessionExpiresAt ? new Date(props.thread.sessionExpiresAt) : null))
const sessionOpen = computed(() => Boolean(windowClosesAt.value && windowClosesAt.value.getTime() > now.value.getTime()))

/** Shown in the last four hours, when it starts to matter. */
const windowWarning = computed(() => {
  if (!sessionOpen.value || !windowClosesAt.value) return null
  const mins = Math.round((windowClosesAt.value.getTime() - now.value.getTime()) / 60_000)
  if (mins > 240) return null
  return mins >= 60 ? `${Math.floor(mins / 60)} h ${mins % 60} min` : `${mins} min`
})

// --- composer -------------------------------------------------------------

const draft = ref('')
const composer = ref<HTMLTextAreaElement | null>(null)

/** Called by the context panel: adds a line to the reply, never sends it. */
function insert(text: string) {
  draft.value = draft.value.trim() ? `${draft.value.trimEnd()}\n${text}` : text
  nextTick(() => composer.value?.focus())
}

defineExpose({ insert })

watch(() => props.thread?.id, () => {
  draft.value = ''
  templateId.value = ''
  parameters.value = []
})

function send() {
  const body = draft.value.trim()
  if (!body || props.sending) return
  emit('send', body)
  draft.value = ''
  scrollToEnd()
}

const approvedTemplates = computed(() => (props.templates ?? []).filter((t) => t.status === 'approved'))
const templateId = ref('')
const parameters = ref<string[]>([])
const chosenTemplate = computed(() => approvedTemplates.value.find((t) => t.id === templateId.value) ?? null)

watch(chosenTemplate, (t) => { parameters.value = t ? t.variables.map(() => '') : [] })

const templatePreview = computed(() => {
  if (!chosenTemplate.value) return ''
  return chosenTemplate.value.variables.reduce(
    (body, v, i) => body.split(v).join(parameters.value[i]?.trim() || v),
    chosenTemplate.value.body,
  )
})

const templateReady = computed(() => Boolean(chosenTemplate.value) && parameters.value.every((p) => p.trim()))

function sendTemplate() {
  if (!chosenTemplate.value || !templateReady.value || props.sending) return
  emit('send-template', { templateId: chosenTemplate.value.id, parameters: parameters.value.map((p) => p.trim()) })
  templateId.value = ''
}

const quickReplies = [
  'Thanks for getting in touch. Let me check that now.',
  'Yes, that size is in stock. Shall I reserve it?',
  'Processing takes 48 hours, then 1–2 business days within Ghana.',
  'Custom orders run 3–5 business days.',
]
</script>

<template>
  <div v-if="!thread" class="flex min-h-0 flex-1 items-center justify-center">
    <UiEmptyState title="Pick a conversation" description="Choose someone on the left to read the thread." />
  </div>

  <div v-else class="flex min-h-0 flex-1 flex-col">
    <!-- Header -->
    <header class="flex shrink-0 items-center gap-3 border-b border-border px-4 py-3">
      <UiAvatar :name="thread.contactName" :src="thread.avatar" :size="40" />
      <div class="min-w-0 flex-1">
        <p class="flex items-center gap-2 truncate text-ui font-medium text-fg-strong">
          {{ thread.contactName }}
          <UiBadge v-if="thread.optedOut" tone="danger" size="sm">
            <PhProhibit :size="12" />
            Opted out
          </UiBadge>
          <UiBadge v-if="thread.isArchived" tone="neutral" size="sm">Archived</UiBadge>
        </p>
        <p class="truncate text-meta text-fg-faint">
          {{ thread.contactPhone }}
          <template v-if="thread.assignedToName"> · with {{ thread.assignedToName }}</template>
        </p>
      </div>
      <NuxtLink
        v-if="thread.linkedOrderReference" :to="`/orders/${thread.linkedOrderReference}`"
        class="hidden items-center gap-1.5 rounded-pill bg-bg-sunken px-2.5 py-1 font-mono text-meta text-fg-muted transition-colors hover:text-fg-strong sm:flex"
      >
        <PhLink :size="13" />
        {{ thread.linkedOrderReference }}
      </NuxtLink>

      <button type="button" class="toolbar-btn xl:hidden" aria-label="Customer, stock and events" @click="emit('toggle-panel')">
        <PhSidebarSimple :size="18" />
      </button>

      <UiDropdown v-if="canReply" label="Conversation actions">
        <template #trigger>
          <button type="button" class="toolbar-btn" aria-label="Conversation actions">
            <PhDotsThreeVertical :size="18" />
          </button>
        </template>

        <UiDropdownItem v-if="thread.assignedToAdminId !== user?.id" @click="emit('assign-to-me')">
          <PhUserCircle :size="16" class="shrink-0 text-fg-faint" />
          Assign to me
        </UiDropdownItem>
        <UiDropdownItem v-else @click="emit('unassign')">
          <PhUserCircle :size="16" class="shrink-0 text-fg-faint" />
          Unassign
        </UiDropdownItem>
        <UiDropdownItem @click="openLink">
          <PhReceipt :size="16" class="shrink-0 text-fg-faint" />
          {{ thread.linkedOrderReference ? 'Change linked order' : 'Link to an order' }}
        </UiDropdownItem>
        <UiDropdownItem @click="createBooking">
          <PhCalendarPlus :size="16" class="shrink-0 text-fg-faint" />
          Create booking from chat
        </UiDropdownItem>
        <UiDropdownItem @click="convertToDiy">
          <PhHammer :size="16" class="shrink-0 text-fg-faint" />
          Convert to DIY order
        </UiDropdownItem>
        <span class="my-1 block h-px bg-border" />
        <p class="px-3 pb-1 pt-1.5 text-micro uppercase tracking-wide text-fg-faint">Topic</p>
        <UiDropdownItem v-for="t in topics" :key="t.value" @click="emit('set-topic', t.value)">
          <PhTag :size="16" class="shrink-0" :class="thread.topic === t.value ? 'text-accent' : 'text-fg-faint'" />
          {{ t.label }}
        </UiDropdownItem>
        <span class="my-1 block h-px bg-border" />
        <UiDropdownItem v-if="!thread.isArchived" @click="emit('archive')">
          <PhArchive :size="16" class="shrink-0 text-fg-faint" />
          Archive conversation
        </UiDropdownItem>
        <UiDropdownItem v-else @click="emit('reopen')">
          <PhArrowCounterClockwise :size="16" class="shrink-0 text-fg-faint" />
          Reopen conversation
        </UiDropdownItem>
      </UiDropdown>
    </header>

    <!-- Messages -->
    <div ref="scroller" class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
      <p v-if="!messages.length" class="py-10 text-center text-meta text-fg-faint">No messages in this conversation yet.</p>

      <div v-for="g in grouped" :key="g.day" class="mb-4 last:mb-0">
        <p class="mb-3 text-center text-meta text-fg-faint">{{ formatDate(g.day) }}</p>

        <ul class="flex flex-col gap-3">
          <li
            v-for="(run, ri) in g.runs" :key="ri"
            class="flex gap-2"
            :class="run.direction === 'outbound' ? 'justify-end' : 'justify-start'"
          >
            <UiAvatar
              v-if="run.direction === 'inbound'"
              :name="thread.contactName" :src="thread.avatar" :size="28" class="mt-0.5"
            />

            <div
              class="flex max-w-[min(78%,30rem)] flex-col gap-1"
              :class="run.direction === 'outbound' && 'items-end'"
            >
              <template v-for="m in run.items" :key="m.id">
                <!-- Who sent it, when it wasn't a person at this inbox -->
                <p
                  v-if="m.source && sourceLabel[m.source]"
                  class="flex items-center gap-1 text-micro uppercase tracking-wide text-fg-faint"
                >
                  <component :is="sourceLabel[m.source]!.icon" :size="11" />
                  {{ sourceLabel[m.source]!.label }}
                </p>

                <!-- Draft: an intern's reply waiting for someone who can send -->
                <div
                  v-if="isDraft(m)"
                  class="w-full rounded-lg border border-dashed border-accent/50 bg-bg-sunken px-3.5 py-2.5"
                >
                  <p class="text-micro uppercase tracking-wide text-fg-faint">
                    Draft{{ m.draftedByName ? ` by ${m.draftedByName}` : '' }} · not sent
                  </p>
                  <textarea
                    v-if="editingDraft === m.id" v-model="draftEdit" rows="3"
                    aria-label="Edit draft" class="field mt-1.5 w-full resize-y"
                  />
                  <p v-else class="mt-1 whitespace-pre-line text-ui leading-relaxed text-fg">{{ m.body }}</p>
                  <div class="mt-2 flex flex-wrap justify-end gap-1.5">
                    <UiButton v-if="canDiscard(m)" variant="ghost" size="sm" @click="emit('discard-draft', m.id)">Discard</UiButton>
                    <template v-if="canReply">
                      <UiButton v-if="editingDraft !== m.id" variant="secondary" size="sm" @click="startEditing(m)">Edit</UiButton>
                      <UiButton size="sm" :disabled="!sessionOpen || sending" @click="sendDraft(m)">Send</UiButton>
                    </template>
                  </div>
                </div>

                <!-- System line: a reaction, or a message type the inbox can't show -->
                <div
                  v-else-if="m.kind === 'system'"
                  class="rounded-lg border border-dashed border-border bg-bg-sunken px-3.5 py-2.5"
                >
                  <p class="whitespace-pre-line text-meta leading-relaxed text-fg-muted">{{ m.body }}</p>
                </div>

                <!-- Attachment -->
                <component
                  :is="m.attachment.url ? 'a' : 'div'"
                  v-else-if="m.attachment"
                  :href="m.attachment.url ?? undefined" target="_blank" rel="noopener"
                  class="flex flex-col gap-1.5 rounded-lg px-3.5 py-2.5"
                  :class="m.direction === 'outbound'
                    ? 'bg-primary text-primary-fg'
                    : 'border border-border bg-bg-elevated text-fg'"
                >
                  <span class="flex items-center gap-2.5">
                    <component
                      :is="m.attachment.mimeType.startsWith('image/') ? PhImage : PhFileText"
                      :size="18" class="shrink-0 opacity-70"
                    />
                    <span class="truncate text-ui" :class="m.attachment.url && 'underline-offset-4 hover:underline'">
                      {{ m.attachment.url ? m.attachment.filename : `${m.attachment.filename} (fetching…)` }}
                    </span>
                  </span>
                  <span v-if="m.body" class="text-ui leading-relaxed">{{ m.body }}</span>
                </component>

                <!-- Text, template or button message -->
                <div
                  v-else
                  class="whitespace-pre-line rounded-lg px-3.5 py-2.5 text-ui leading-relaxed"
                  :class="[
                    m.direction === 'outbound'
                      ? 'bg-primary text-primary-fg'
                      : 'border border-border bg-bg-elevated text-fg',
                    m.status === 'failed' && 'opacity-60',
                  ]"
                >
                  <p v-if="m.templateName" class="mb-1 font-mono text-micro opacity-70">{{ m.templateName }}</p>
                  {{ m.body }}
                  <a
                    v-if="m.cta" :href="m.cta.url" target="_blank" rel="noopener"
                    class="mt-2 block rounded-md border border-current/30 px-3 py-1.5 text-center text-meta font-medium"
                  >{{ m.cta.button }}</a>
                </div>

                <!-- Per-message problems: never let a failed or undelivered reply look sent -->
                <p v-if="m.status === 'failed'" class="flex items-start gap-1 text-meta text-danger">
                  <PhWarningCircle :size="13" class="mt-0.5 shrink-0" />
                  Not delivered{{ m.error ? `: ${m.error}` : '' }}
                </p>
                <p v-else-if="m.simulated && m.direction === 'outbound' && !isDraft(m)" class="text-micro text-warning">
                  Not delivered: WhatsApp isn’t connected
                </p>
              </template>

              <!-- One timestamp per run, as the frame shows -->
              <p class="flex items-center gap-1 text-micro text-fg-faint">
                <template v-if="run.direction === 'outbound' && run.items[run.items.length - 1]!.sentByName">
                  {{ run.items[run.items.length - 1]!.sentByName }} ·
                </template>
                {{ formatTime(run.items[run.items.length - 1]!.sentAt) }}
                <template v-if="run.direction === 'outbound' && statusIcon(run.items[run.items.length - 1]!)">
                  <component
                    :is="statusIcon(run.items[run.items.length - 1]!)" :size="12"
                    :class="{
                      'text-info': run.items[run.items.length - 1]!.status === 'read',
                      'text-danger': run.items[run.items.length - 1]!.status === 'failed',
                    }"
                    :aria-label="run.items[run.items.length - 1]!.status"
                  />
                </template>
              </p>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Session window notices -->
    <div
      v-if="!sessionOpen"
      class="flex shrink-0 items-start gap-2.5 border-t border-warning/30 bg-warning-soft px-4 py-2.5 text-meta text-warning"
    >
      <PhWarningCircle :size="15" class="mt-px shrink-0" />
      <p>
        {{ thread.sessionExpiresAt ? 'The 24-hour reply window has closed.' : 'This customer hasn’t written to the brand yet.' }}
        WhatsApp only permits an approved template until they message again.
      </p>
    </div>
    <div
      v-else-if="windowWarning"
      class="flex shrink-0 items-center gap-2.5 border-t border-border bg-bg-sunken px-4 py-2 text-meta text-fg-muted"
    >
      <PhClock :size="15" class="shrink-0" />
      Free-form replies are allowed for another {{ windowWarning }}.
    </div>

    <!-- Composer: free text inside the window -->
    <div v-if="sessionOpen && (canReply || canDraft)" class="shrink-0 border-t border-border p-3">
      <div class="flex flex-wrap gap-1.5 pb-2">
        <button
          v-for="q in quickReplies" :key="q"
          type="button"
          class="truncate rounded-pill border border-border px-2.5 py-1 text-meta text-fg-muted transition-colors hover:bg-bg-sunken hover:text-fg-strong"
          @click="draft = q"
        >{{ q }}</button>
      </div>

      <form class="flex items-end gap-2" @submit.prevent="send">
        <textarea
          ref="composer"
          v-model="draft" rows="1"
          :placeholder="canReply ? 'Write a message…' : 'Write a draft for a Staff member to send…'"
          aria-label="Message"
          class="field max-h-32 min-h-[44px] flex-1 resize-none py-3"
          @keydown.enter.exact.prevent="send"
        />
        <UiButton type="submit" size="md" :disabled="!draft.trim() || sending" :loading="sending" class="shrink-0" :class="canReply && '!px-3'">
          <template v-if="canReply">
            <PhPaperPlaneTilt :size="18" />
            <span class="sr-only">Send</span>
          </template>
          <template v-else>Save draft</template>
        </UiButton>
      </form>
    </div>

    <!-- Composer: a template outside the window -->
    <div v-else-if="!sessionOpen && canReply" class="shrink-0 border-t border-border p-3">
      <p v-if="thread.optedOut" class="text-meta text-fg-muted">
        This customer replied STOP, so templates are paused until they write again.
      </p>
      <p v-else-if="!approvedTemplates.length" class="text-meta text-fg-muted">
        No approved templates yet. They’re created in WhatsApp Manager; refresh them on the Templates tab.
      </p>
      <form v-else class="flex flex-col gap-2.5" @submit.prevent="sendTemplate">
        <div>
          <label class="field-label" for="template-pick">Template</label>
          <select id="template-pick" v-model="templateId" class="field w-full">
            <option value="" disabled>Choose an approved template…</option>
            <option v-for="t in approvedTemplates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.language }})</option>
          </select>
        </div>
        <template v-if="chosenTemplate">
          <div v-if="chosenTemplate.variables.length" class="grid gap-2 sm:grid-cols-2">
            <div v-for="(v, i) in chosenTemplate.variables" :key="v">
              <label class="field-label" :for="`template-var-${i}`">Value for {{ v }}</label>
              <input :id="`template-var-${i}`" v-model="parameters[i]" type="text" class="field w-full" maxlength="1024">
            </div>
          </div>
          <p class="rounded-lg bg-bg-sunken px-3 py-2.5 text-ui leading-relaxed text-fg">{{ templatePreview }}</p>
          <div class="flex justify-end">
            <UiButton type="submit" size="sm" :disabled="!templateReady || sending" :loading="sending">
              <PhPaperPlaneTilt :size="16" />
              Send template
            </UiButton>
          </div>
        </template>
      </form>
    </div>

    <UiModal v-model:open="linkOpen" size="sm" title="Link to an order" description="Type the order number the customer is asking about.">
      <form class="flex flex-col gap-2" @submit.prevent="saveLink">
        <label class="field-label" for="link-reference">Order number</label>
        <input id="link-reference" v-model="linkReference" type="text" class="field w-full font-mono" placeholder="GCT-…">
        <p class="text-meta text-fg-faint">Leave it empty to remove the link.</p>
      </form>
      <template #footer>
        <UiButton variant="secondary" size="sm" @click="linkOpen = false">Cancel</UiButton>
        <UiButton size="sm" @click="saveLink">Save</UiButton>
      </template>
    </UiModal>
  </div>
</template>
