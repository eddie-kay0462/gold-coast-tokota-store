<script setup lang="ts">
import { PhArrowLeft, PhGear, PhPlugs } from '@phosphor-icons/vue'
import type { ChatMessage, ChatThread, MessageTemplate } from '~/types'

/**
 * WhatsApp inbox — Figma node 29:8158.
 *
 * SIMULATED. Nothing here sends or receives a real message.
 *
 * README Feature 6 scopes WhatsApp as a deep link only — `wa.me/<number>`,
 * no API integration. A two-way inbox needs the Business Cloud API, a verified
 * WABA, approved templates and a webhook receiver on the Laravel side, none of
 * which exist. So this is built against the real Cloud API's shape (message
 * direction, delivery receipts, the 24-hour session window, template approval
 * states) and served from fixtures, and says so on every screen. The
 * alternative — a convincing inbox with no disclosure — is how someone ends up
 * believing a customer was replied to when they were not.
 *
 * The conversations themselves are real scenarios this business handles: a
 * size exchange inside the 7-day window, a sold-out Sip & Paint, a Lagos
 * wholesale enquiry, a DIY order with foot measurements, a MoMo confirmation.
 */
useHead({ title: 'Inbox' })

const { useAdminList } = useAdminApi()
const { can } = useAuth()
const { formatRelative } = useFormatters()

const { items: threads } = useAdminList<ChatThread>('inbox-threads', '/admin/inbox/threads')
const { items: allMessages } = useAdminList<ChatMessage>('inbox-messages', '/admin/inbox/messages')
const { items: templates } = useAdminList<MessageTemplate>('inbox-templates', '/admin/inbox/templates')

const tab = ref<'conversations' | 'templates'>('conversations')
const activeId = ref<string | null>(null)

/** Locally appended sends, kept separate so fixtures stay immutable. */
const localMessages = ref<ChatMessage[]>([])

watchEffect(() => {
  if (!activeId.value && threads.value.length) activeId.value = threads.value[0]!.id
})

const activeThread = computed(() => threads.value.find((t) => t.id === activeId.value) ?? null)

const activeMessages = computed(() =>
  [...allMessages.value, ...localMessages.value]
    .filter((m) => m.threadId === activeId.value)
    .sort((a, b) => new Date(a.sentAt).getTime() - new Date(b.sentAt).getTime()),
)

function send(body: string) {
  if (!activeId.value) return
  // Appended locally and flagged `simulated`. It goes nowhere.
  localMessages.value.push({
    id: `local-${Date.now()}`,
    threadId: activeId.value,
    direction: 'outbound',
    kind: 'text',
    body,
    attachment: null,
    status: 'sent',
    simulated: true,
    sentAt: new Date().toISOString(),
  })
}

/** Below lg the two panes become one — list, then thread. */
const showThreadOnMobile = ref(false)
function selectThread(id: string) {
  activeId.value = id
  showThreadOnMobile.value = true
}

const templateTone = { approved: 'success', pending: 'warning', rejected: 'danger' } as const
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

    <!-- Honesty banner. Not dismissible: this is the page's most important fact. -->
    <div class="flex items-start gap-3 rounded-lg border border-warning/30 bg-warning-soft px-4 py-3">
      <PhPlugs :size="20" class="mt-px shrink-0 text-warning" />
      <div class="min-w-0">
        <p class="text-ui font-medium text-warning">Simulated — the WhatsApp Business Cloud API is not connected</p>
        <p class="mt-1 text-meta text-warning/90">
          These conversations are sample data. Messages sent from here go nowhere. Wiring this up
          needs a verified Business account, Cloud API credentials and a webhook receiver on the
          API — the original scope covered the <code class="font-mono">wa.me</code> deep link
          only, so this is additional work.
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
      <!-- List: always visible from lg, otherwise only when no thread is open -->
      <div
        class="w-full shrink-0 border-border lg:block lg:w-[340px] lg:border-r"
        :class="showThreadOnMobile ? 'hidden' : 'block'"
      >
        <InboxThreadList :threads="threads" :active-id="activeId" @select="selectThread" />
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
        <InboxMessageThread :thread="activeThread" :messages="activeMessages" @send="send" />
      </div>
    </div>

    <!-- Templates -->
    <div v-else class="admin-stack">
      <p class="text-ui text-fg-muted">
        Outside the 24-hour reply window, WhatsApp only permits pre-approved templates. Each one
        is reviewed by Meta before it can be used.
      </p>

      <ul class="grid gap-4 lg:grid-cols-2">
        <li v-for="t in templates" :key="t.id" class="card card-pad">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate font-mono text-ui text-fg-strong">{{ t.name }}</p>
              <p class="mt-0.5 text-meta text-fg-faint">
                {{ t.category }} · {{ t.language }} · updated {{ formatRelative(t.updatedAt) }}
              </p>
            </div>
            <UiBadge :tone="templateTone[t.status]" dot>{{ t.status }}</UiBadge>
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

      <p v-if="!can('inbox.templates')" class="text-meta text-fg-faint">
        Editing templates needs an Admin account.
      </p>
    </div>
  </div>
</template>
