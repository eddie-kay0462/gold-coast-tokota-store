<script setup lang="ts">
import { PhArrowLeft, PhArrowsClockwise, PhGear, PhInfo, PhPlugs } from '@phosphor-icons/vue'
import type { ChatMessage, ChatThread, MessageTemplate, OrderLineDraft, PlacedWhatsAppOrder } from '~/types'

/**
 * WhatsApp inbox, Figma node 29:8158.
 *
 * Live on `/admin/inbox/*` (the WhatsApp Cloud API with coexistence: replies
 * typed on the brand phone arrive here too). Three honest states, each said
 * on screen:
 *
 *   live + connected   real conversations, replies are delivered
 *   live, no number    real inbox, but the API has no Cloud API credentials,
 *                      so replies are recorded and marked simulated
 *   fixtures           sample conversations; nothing leaves the page
 *
 * Freshness is polling, as the README specifies: the thread list every 15 s,
 * the open thread every 5 s (only what is new), both paused while the tab is
 * hidden.
 */
useHead({ title: 'Inbox' })

const { adminFetch } = useAdminApi()
const { can, user } = useAuth()
const { formatRelative } = useFormatters()
const toast = useToast()

/** Shared with the layout, so the sidebar badge follows the inbox. */
const unreadBadge = useState<number | null>('inbox-unread', () => null)

const threads = ref<ChatThread[]>([])
const templates = ref<MessageTemplate[]>([])
const source = ref<'live' | 'fixture'>('fixture')
const connected = ref(false)
const loading = ref(true)

const tab = ref<'conversations' | 'templates'>('conversations')
const activeId = ref<string | null>(null)

const activeThread = computed(() => threads.value.find((t) => t.id === activeId.value) ?? null)

/** The context panel: a column from xl, a slide-over below it. */
const panelOpen = ref(false)
const threadPane = ref<{ insert: (text: string) => void } | null>(null)

function insertIntoReply(text: string) {
  const closesAt = activeThread.value?.sessionExpiresAt
  if (source.value === 'live' && (!closesAt || new Date(closesAt).getTime() <= Date.now())) {
    // The text box is replaced by the template picker outside the window.
    toast.info('Only a template can be sent now', 'The 24-hour reply window for this chat has closed.')
    return
  }
  threadPane.value?.insert(text)
  panelOpen.value = false
}

async function loadThreads() {
  const res = await adminFetch<ChatThread[]>('/admin/inbox/threads')
  threads.value = res.data
  source.value = res.source
  connected.value = res.source === 'live' && Boolean(res.meta?.connected)
  if (res.source === 'live') unreadBadge.value = Number(res.meta?.unreadTotal ?? 0)
  if (!activeId.value && threads.value.length && import.meta.client && window.innerWidth >= 1024) {
    selectThread(threads.value[0]!.id, false)
  }
}

async function loadTemplates() {
  templates.value = (await adminFetch<MessageTemplate[]>('/admin/inbox/templates')).data
}

onMounted(async () => {
  try {
    await Promise.all([loadThreads(), loadTemplates()])
  } finally {
    loading.value = false
  }
})

usePolling(loadThreads, 15_000)

// --- the open thread ------------------------------------------------------

/** Messages per thread id, so switching back to a thread is instant. */
const messagesByThread = ref<Record<string, ChatMessage[]>>({})
const lastPolledAt = ref<Record<string, string>>({})

const activeMessages = computed(() =>
  [...(messagesByThread.value[activeId.value ?? ''] ?? [])]
    .sort((a, b) => new Date(a.sentAt).getTime() - new Date(b.sentAt).getTime() || Number(a.id) - Number(b.id)),
)

function merge(threadId: string, incoming: ChatMessage[]) {
  const byId = new Map((messagesByThread.value[threadId] ?? []).map((m) => [m.id, m]))
  for (const m of incoming) byId.set(m.id, m)
  messagesByThread.value = { ...messagesByThread.value, [threadId]: [...byId.values()] }
}

function remove(threadId: string, messageId: string) {
  messagesByThread.value = {
    ...messagesByThread.value,
    [threadId]: (messagesByThread.value[threadId] ?? []).filter((m) => m.id !== messageId),
  }
}

async function loadMessages(threadId: string) {
  const known = messagesByThread.value[threadId]
  const since = lastPolledAt.value[threadId]
  const lastId = known?.length ? Math.max(...known.map((m) => Number(m.id) || 0)) : null
  const startedAt = new Date().toISOString()

  const query: Record<string, unknown> = { thread: threadId }
  if (lastId) query.after = lastId
  if (since) query.changed_since = since

  let res = await adminFetch<ChatMessage[]>('/admin/inbox/messages', { query })
  if (res.source === 'fixture') {
    // Fixture mode reads every query key as a filter, so ask for the lot and
    // pick this thread's messages out here.
    res = await adminFetch<ChatMessage[]>('/admin/inbox/messages')
    res = { ...res, data: res.data.filter((m) => m.threadId === threadId) }
  }

  merge(threadId, res.data)
  lastPolledAt.value = { ...lastPolledAt.value, [threadId]: startedAt }
}

const messagePoll = usePolling(async () => {
  if (activeId.value && tab.value === 'conversations') await loadMessages(activeId.value)
}, 5_000)

/** Below lg the two panes become one: list, then thread. */
const showThreadOnMobile = ref(false)

async function selectThread(id: string, openOnMobile = true) {
  activeId.value = id
  if (openOnMobile) showThreadOnMobile.value = true

  await loadMessages(id).catch(() => toast.error('Couldn’t load this conversation', 'Try again in a moment.'))

  const thread = threads.value.find((t) => t.id === id)
  if (thread?.unreadCount && source.value === 'live') {
    thread.unreadCount = 0
    if (unreadBadge.value) unreadBadge.value = Math.max(0, unreadBadge.value - 1)
    adminFetch(`/admin/inbox/threads/${id}/read`, { method: 'POST' }).catch(() => {})
  }
}

// --- sending ----------------------------------------------------------------

const sending = ref(false)

function refusal(err: unknown, fallback: string): string {
  const e = err as { statusCode?: number; data?: { message?: string; errors?: Record<string, string[]> } }
  if (e.statusCode === 401 || e.statusCode === 419) return ''
  return e.data?.message ?? Object.values(e.data?.errors ?? {})[0]?.[0] ?? fallback
}

async function post(path: string, body: Record<string, unknown>, okTitle?: string) {
  if (!activeId.value) return
  if (source.value !== 'live') {
    toast.info('Sample conversation', 'The inbox is showing sample data, so nothing was sent.')
    return
  }
  const threadId = activeId.value
  sending.value = true
  try {
    const { data } = await adminFetch<ChatMessage>(path, { method: 'POST', body })
    merge(threadId, [data])
    if (okTitle) toast.success(okTitle)
    messagePoll.now()
    loadThreads().catch(() => {})
    return data
  } catch (err) {
    const message = refusal(err, 'The message wasn’t sent. Try again in a moment.')
    if (message) toast.error('Not sent', message)
  } finally {
    sending.value = false
  }
}

function sendText(body: string) {
  return can('inbox.reply')
    ? post(`/admin/inbox/threads/${activeId.value}/messages`, { body })
    : post(`/admin/inbox/threads/${activeId.value}/drafts`, { body }, 'Draft saved for a Staff member to send')
}

function sendTemplate(payload: { templateId: string; parameters: string[] }) {
  return post(`/admin/inbox/threads/${activeId.value}/messages`, {
    template_id: Number(payload.templateId),
    parameters: payload.parameters,
  })
}

function sendDraft(payload: { id: string; body: string }) {
  return post(`/admin/inbox/messages/${payload.id}/send`, { body: payload.body })
}

async function discardDraft(id: string) {
  if (!activeId.value) return
  const threadId = activeId.value
  try {
    await adminFetch(`/admin/inbox/messages/${id}`, { method: 'DELETE' })
    remove(threadId, id)
  } catch (err) {
    const message = refusal(err, 'The draft wasn’t discarded.')
    if (message) toast.error('Not discarded', message)
  }
}

async function updateThread(changes: Record<string, unknown>, okTitle: string) {
  if (!activeThread.value || source.value !== 'live') {
    toast.info('Sample conversation', 'The inbox is showing sample data, so nothing was saved.')
    return
  }
  try {
    const { data } = await adminFetch<ChatThread>(`/admin/inbox/threads/${activeThread.value.id}`, { method: 'PATCH', body: changes })
    threads.value = threads.value.map((t) => (t.id === data.id ? data : t))
    toast.success(okTitle)
  } catch (err) {
    const message = refusal(err, 'Nothing was saved. Try again in a moment.')
    if (message) toast.error('Not saved', message)
  }
}

const threadActions = {
  archive: () => updateThread({ status: 'closed' }, 'Conversation archived'),
  reopen: () => updateThread({ status: 'open' }, 'Conversation reopened'),
  assignToMe: () => updateThread({ assigned_admin_id: user.value?.id }, 'Assigned to you'),
  unassign: () => updateThread({ assigned_admin_id: null }, 'Unassigned'),
  linkOrder: (reference: string | null) => updateThread({ linked_order_reference: reference }, reference ? `Linked to ${reference}` : 'Order unlinked'),
  setTopic: (topic: ChatThread['topic']) => updateThread({ topic }, 'Topic updated'),
}

// --- Order on WhatsApp ------------------------------------------------------

const builderOpen = ref(false)
/** Lines added from the Stock tab, waiting to join the builder. */
const pendingLines = ref<OrderLineDraft[]>([])
const panels = ref<{ reload: () => void }[]>([])

function addToOrder(line: OrderLineDraft) {
  pendingLines.value = [...pendingLines.value, line]
  builderOpen.value = true
  panelOpen.value = false
}

function afterOrderChange() {
  if (activeId.value) messagePoll.now()
  loadThreads().catch(() => {})
  panels.value.forEach((p) => p?.reload())
}

function onPlaced(result: PlacedWhatsAppOrder) {
  if (result.message && activeId.value) merge(activeId.value, [result.message])
  afterOrderChange()
}

async function resendLink(reference: string) {
  try {
    const { data } = await adminFetch<PlacedWhatsAppOrder>(`/admin/inbox/orders/${reference}/resend-link`, { method: 'POST' })
    if (data.message && activeId.value) merge(activeId.value, [data.message])
    toast[data.warning ? 'info' : 'success'](data.warning ? 'New link made, not sent' : 'New payment link sent', data.warning ?? undefined)
    afterOrderChange()
  } catch (err) {
    const message = refusal(err, 'No new link was made.')
    if (message) toast.error('Not resent', message)
  }
}

async function cancelOrder(reference: string) {
  if (!window.confirm(`Cancel order ${reference}? Its stock is released at once, and the payment link should no longer be used.`)) return
  try {
    await adminFetch(`/admin/inbox/orders/${reference}/cancel`, { method: 'POST' })
    toast.success(`Order ${reference} cancelled`, 'The held stock is back on sale.')
    afterOrderChange()
  } catch (err) {
    const message = refusal(err, 'The order wasn’t cancelled.')
    if (message) toast.error('Not cancelled', message)
  }
}

// --- templates ------------------------------------------------------------

const syncing = ref(false)

async function syncTemplates() {
  syncing.value = true
  try {
    templates.value = (await adminFetch<MessageTemplate[]>('/admin/inbox/templates/sync', { method: 'POST' })).data
    toast.success('Templates refreshed from WhatsApp')
  } catch (err) {
    const message = refusal(err, 'Couldn’t reach WhatsApp. Try again in a moment.')
    if (message) toast.error('Not refreshed', message)
  } finally {
    syncing.value = false
  }
}

const templateTone = { approved: 'success', pending: 'warning', rejected: 'danger', paused: 'warning', disabled: 'danger' } as const
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col gap-4">
    <UiPageHeader title="Inbox" description="Customer conversations on the brand's main ordering channel.">
      <template #actions>
        <UiPermissionGate capability="settings.view" quiet>
          <UiButton variant="secondary" size="sm" to="/settings/whatsapp">
            <PhGear :size="16" />
            Configure
          </UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <!-- Honesty banners. Not dismissible: whether a reply reaches the customer is the page's most important fact. -->
    <div
      v-if="!loading && source === 'fixture'"
      class="flex items-start gap-3 rounded-lg border border-warning/30 bg-warning-soft px-4 py-3"
    >
      <PhPlugs :size="20" class="mt-px shrink-0 text-warning" />
      <div class="min-w-0">
        <p class="text-ui font-medium text-warning">Sample conversations: the inbox API isn’t answering</p>
        <p class="mt-1 text-meta text-warning/90">
          These are examples, and messages sent from here go nowhere. Once the API is reachable this
          page shows the real WhatsApp inbox.
        </p>
      </div>
    </div>
    <div
      v-else-if="!loading && !connected"
      class="flex items-start gap-3 rounded-lg border border-info/30 bg-info-soft px-4 py-3"
    >
      <PhInfo :size="20" class="mt-px shrink-0 text-info" />
      <div class="min-w-0">
        <p class="text-ui font-medium text-info">The WhatsApp number isn’t connected yet</p>
        <p class="mt-1 text-meta text-info/90">
          The inbox works, but replies are recorded here and not delivered. They’re marked
          “not delivered”. A Super Admin connects the brand number with Meta to switch this on.
        </p>
      </div>
    </div>

    <div class="flex items-center gap-5 border-b border-border">
      <button
        v-for="t in (['conversations', 'templates'] as const)" :key="t"
        type="button" class="-mb-px border-b-2 pb-2.5 text-ui capitalize transition-colors"
        :class="tab === t ? 'border-accent font-medium text-fg-strong' : 'border-transparent text-fg-muted hover:text-fg-strong'"
        @click="tab = t"
      >
        {{ t }}
        <span class="ml-1.5 text-meta text-fg-faint">
          {{ t === 'conversations' ? threads.length : templates.length }}
        </span>
      </button>
    </div>

    <!-- Conversations -->
    <div v-if="tab === 'conversations'" class="card flex min-h-[36rem] flex-1 overflow-hidden">
      <div
        class="w-full shrink-0 border-border lg:block lg:w-[340px] lg:border-r"
        :class="showThreadOnMobile ? 'hidden' : 'block'"
      >
        <p v-if="loading" class="p-6 text-center text-meta text-fg-faint">Loading conversations…</p>
        <UiEmptyState
          v-else-if="!threads.length"
          title="No conversations yet"
          description="When a customer messages the brand on WhatsApp, the chat appears here."
        />
        <InboxThreadList v-else :threads="threads" :active-id="activeId" @select="selectThread" />
      </div>

      <div class="min-w-0 flex-1 flex-col lg:flex" :class="showThreadOnMobile ? 'flex' : 'hidden'">
        <button
          type="button"
          class="flex shrink-0 items-center gap-2 border-b border-border px-4 py-2.5 text-ui text-fg-muted lg:hidden"
          @click="showThreadOnMobile = false"
        >
          <PhArrowLeft :size="16" />
          All conversations
        </button>
        <InboxMessageThread
          ref="threadPane"
          :thread="activeThread"
          :live="source === 'live'"
          :messages="activeMessages"
          :templates="templates"
          :sending="sending"
          @send="sendText"
          @send-template="sendTemplate"
          @send-draft="sendDraft"
          @discard-draft="discardDraft"
          @archive="threadActions.archive"
          @reopen="threadActions.reopen"
          @assign-to-me="threadActions.assignToMe"
          @unassign="threadActions.unassign"
          @link-order="threadActions.linkOrder"
          @set-topic="threadActions.setTopic"
          @toggle-panel="panelOpen = !panelOpen"
          @new-order="builderOpen = true"
        />
      </div>

      <InboxContextPanel
        v-if="activeThread"
        :ref="(el) => { if (el) panels[0] = el as unknown as { reload: () => void } }"
        class="hidden w-[320px] shrink-0 border-l border-border xl:flex"
        :thread="activeThread" :live="source === 'live'"
        @insert="insertIntoReply"
        @add-to-order="addToOrder" @resend-link="resendLink" @cancel-order="cancelOrder"
      />
    </div>

    <!-- Templates -->
    <div v-else class="admin-stack">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-prose text-ui text-fg-muted">
          Outside the 24-hour reply window, WhatsApp only permits pre-approved templates. Each one
          is written in Meta’s WhatsApp Manager and reviewed by Meta before it can be used.
        </p>
        <UiPermissionGate capability="inbox.templates" quiet>
          <UiButton variant="secondary" size="sm" :loading="syncing" @click="syncTemplates">
            <PhArrowsClockwise :size="16" />
            Refresh from WhatsApp
          </UiButton>
        </UiPermissionGate>
      </div>

      <UiEmptyState
        v-if="!templates.length"
        title="No templates yet"
        description="Templates are created in WhatsApp Manager. Once approved, refresh to bring them in."
      />

      <ul v-else class="grid gap-4 lg:grid-cols-2">
        <li v-for="t in templates" :key="t.id" class="card card-pad">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate font-mono text-ui text-fg-strong">{{ t.name }}</p>
              <p class="mt-0.5 text-meta text-fg-faint">
                {{ t.category }} · {{ t.language }} · updated {{ formatRelative(t.updatedAt) }}
              </p>
            </div>
            <UiBadge :tone="templateTone[t.status as keyof typeof templateTone] ?? 'neutral'" dot>{{ t.status }}</UiBadge>
          </div>

          <p class="mt-3 rounded-lg bg-bg-sunken px-3 py-2.5 text-ui leading-relaxed text-fg">
            {{ t.body }}
          </p>

          <div class="mt-2.5 flex flex-wrap gap-1.5">
            <UiBadge v-for="v in t.variables" :key="v" tone="outline" size="sm">{{ v }}</UiBadge>
          </div>

          <p v-if="t.rejectionReason" class="mt-2.5 text-meta text-danger">
            Rejected: {{ t.rejectionReason }}
          </p>
        </li>
      </ul>
    </div>

    <!-- Below xl the panel slides over the thread -->
    <Transition
      enter-active-class="transition duration-200" enter-from-class="translate-x-full opacity-0"
      leave-active-class="transition duration-150" leave-to-class="translate-x-full opacity-0"
    >
      <div v-if="panelOpen && activeThread" class="fixed inset-y-0 right-0 z-40 flex w-full max-w-sm flex-col border-l border-border bg-bg-elevated shadow-xl xl:hidden">
        <InboxContextPanel
          :ref="(el) => { if (el) panels[1] = el as unknown as { reload: () => void } }"
          class="flex min-h-0 flex-1"
          :thread="activeThread" :live="source === 'live'"
          @insert="insertIntoReply" @close="panelOpen = false"
          @add-to-order="addToOrder" @resend-link="resendLink" @cancel-order="cancelOrder"
        />
      </div>
    </Transition>
    <div v-if="panelOpen && activeThread" class="fixed inset-0 z-30 bg-black/30 xl:hidden" aria-hidden="true" @click="panelOpen = false" />

    <InboxOrderBuilder
      v-model:open="builderOpen"
      :thread="activeThread"
      :extra-lines="pendingLines"
      @consumed-extra="pendingLines = []"
      @placed="onPlaced"
    />
  </div>
</template>
