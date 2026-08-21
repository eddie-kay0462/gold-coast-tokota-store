<script setup lang="ts">
import {
  PhFileText, PhImage, PhPaperPlaneTilt, PhPaperclip, PhDotsThreeVertical,
  PhCheck, PhChecks, PhWarningCircle, PhLink, PhReceipt, PhCalendarPlus,
  PhHammer, PhArchive,
} from '@phosphor-icons/vue'
import type { ChatMessage, ChatThread } from '~/types'
import { orders as orderFixtures } from '~/fixtures'

/** Thread view and composer — Figma node 29:8158 right pane. */
const props = defineProps<{ thread: ChatThread | null; messages: ChatMessage[] }>()
const emit = defineEmits<{ (e: 'send', body: string): void }>()

const { formatDate, formatTime, formatRelative, now } = useFormatters()
const toast = useToast()

/**
 * A conversation is rarely just a conversation — it is usually about an order,
 * a booking, or a custom job that needs creating. These actions carry the
 * thread's context into the matching admin screen instead of leaving the
 * operator to retype it, which is the difference between an inbox and a
 * read-only transcript.
 *
 * They are one-way today: the record links back to the thread, and the thread
 * links forward to the record. Persisting the link needs the admin API.
 */
const linkedOrder = computed(() =>
  props.thread?.linkedOrderId
    ? orderFixtures.find((o) => o.id === props.thread!.linkedOrderId) ?? null
    : null,
)

function attachToOrder() {
  toast.info(
    'Pick an order to attach',
    `Opening orders filtered to ${props.thread?.contactName}. Saving the link needs the admin API.`,
  )
  navigateTo({ path: '/orders', query: { q: props.thread?.contactName } })
}

function createBooking() {
  toast.info(
    'Starting a booking',
    `Contact details from ${props.thread?.contactName} will prefill once the bookings endpoint exists.`,
  )
  navigateTo({ path: '/bookings', query: { from: props.thread?.id } })
}

function convertToDiy() {
  toast.info(
    'Starting a DIY order',
    'DIY orders are queue-based and never capacity-limited, so this is always accepted.',
  )
  navigateTo({ path: '/bookings', query: { from: props.thread?.id, type: 'diy_order' } })
}

function archive() {
  toast.success('Conversation archived', 'Not persisted — the admin API is still to be built.')
}

const draft = ref('')
const scroller = ref<HTMLElement | null>(null)

/**
 * Group by day, then by consecutive run of the same direction. The frame shows
 * one avatar per run beside the first bubble, not one per message — repeating
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

/**
 * The Cloud API only allows free-form replies within 24 hours of the
 * customer's last message; past that you must send an approved template.
 * Surfacing the window is the difference between a mock and something an
 * operator could actually work from.
 */
const sessionOpen = computed(() =>
  props.thread ? new Date(props.thread.sessionExpiresAt).getTime() > now.value.getTime() : false,
)

async function scrollToEnd() {
  await nextTick()
  if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight
}
watch(() => props.thread?.id, scrollToEnd, { immediate: true })
watch(() => props.messages.length, scrollToEnd)

function send() {
  const body = draft.value.trim()
  if (!body) return
  emit('send', body)
  draft.value = ''
  scrollToEnd()
  // Never let a send look successful when nothing left the building.
  toast.info('Message added locally', 'Not delivered — the WhatsApp Cloud API is not connected.')
}

const quickReplies = [
  'Thanks for reaching out — checking that now.',
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
      <UiAvatar :name="thread.contactName" :src="thread.avatar" :size="40" :online="thread.isOnline" />
      <div class="min-w-0 flex-1">
        <p class="truncate text-ui font-medium text-fg-strong">{{ thread.contactName }}</p>
        <p class="truncate text-meta text-fg-faint">
          {{ thread.contactPhone }} ·
          {{ thread.isOnline ? 'Online' : `Last seen ${formatRelative(thread.lastSeenAt)}` }}
        </p>
      </div>
      <NuxtLink
        v-if="linkedOrder" :to="`/orders/${linkedOrder.id}`"
        class="hidden items-center gap-1.5 rounded-pill bg-bg-sunken px-2.5 py-1 font-mono text-meta text-fg-muted transition-colors hover:text-fg-strong sm:flex"
      >
        <PhLink :size="13" />
        {{ linkedOrder.reference }}
      </NuxtLink>

      <UiDropdown label="Conversation actions">
        <template #trigger>
          <button type="button" class="toolbar-btn" aria-label="Conversation actions">
            <PhDotsThreeVertical :size="18" />
          </button>
        </template>

        <UiDropdownItem @click="attachToOrder">
          <PhReceipt :size="16" class="shrink-0 text-fg-faint" />
          {{ linkedOrder ? 'Change linked order' : 'Attach to order' }}
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
        <UiDropdownItem @click="archive">
          <PhArchive :size="16" class="shrink-0 text-fg-faint" />
          Archive conversation
        </UiDropdownItem>
      </UiDropdown>
    </header>

    <!-- Messages -->
    <div ref="scroller" class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
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
                <!-- System message: the automated greeting -->
                <div
                  v-if="m.kind === 'system'"
                  class="rounded-lg border border-dashed border-border bg-bg-sunken px-3.5 py-2.5"
                >
                  <p class="text-micro uppercase tracking-wide text-fg-faint">Automated greeting</p>
                  <p class="mt-1 whitespace-pre-line text-meta leading-relaxed text-fg-muted">{{ m.body }}</p>
                </div>

                <!-- Attachment -->
                <div
                  v-else-if="m.attachment"
                  class="flex items-center gap-2.5 rounded-lg px-3.5 py-2.5"
                  :class="m.direction === 'outbound'
                    ? 'bg-primary text-primary-fg'
                    : 'border border-border bg-bg-elevated text-fg'"
                >
                  <component
                    :is="m.attachment.mimeType.startsWith('image/') ? PhImage : PhFileText"
                    :size="18" class="shrink-0 opacity-70"
                  />
                  <span class="truncate text-ui">{{ m.attachment.filename }}</span>
                </div>

                <!-- Text -->
                <div
                  v-else
                  class="rounded-lg px-3.5 py-2.5 text-ui leading-relaxed"
                  :class="m.direction === 'outbound'
                    ? 'bg-primary text-primary-fg'
                    : 'border border-border bg-bg-elevated text-fg'"
                >
                  {{ m.body }}
                </div>
              </template>

              <!-- One timestamp per run, as the frame shows -->
              <p class="flex items-center gap-1 text-micro text-fg-faint">
                {{ formatTime(run.items[run.items.length - 1]!.sentAt) }}
                <template v-if="run.direction === 'outbound'">
                  <component
                    :is="run.items[run.items.length - 1]!.status === 'read' ? PhChecks : PhCheck" :size="12"
                    :class="run.items[run.items.length - 1]!.status === 'read' && 'text-info'"
                  />
                  <span v-if="run.items.some((m) => m.simulated)" class="ml-1 text-warning">simulated</span>
                </template>
              </p>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Session window notice -->
    <div
      v-if="!sessionOpen"
      class="flex shrink-0 items-start gap-2.5 border-t border-warning/30 bg-warning-soft px-4 py-2.5 text-meta text-warning"
    >
      <PhWarningCircle :size="15" class="mt-px shrink-0" />
      <p>
        The 24-hour reply window has closed. WhatsApp only permits an approved template message
        outside it — a free-form reply would be rejected by the API.
      </p>
    </div>

    <!-- Composer (Figma 29:9034) -->
    <div class="shrink-0 border-t border-border p-3">
      <div class="flex flex-wrap gap-1.5 pb-2">
        <button
          v-for="q in quickReplies" :key="q"
          type="button"
          class="truncate rounded-pill border border-border px-2.5 py-1 text-meta text-fg-muted transition-colors hover:bg-bg-sunken hover:text-fg-strong"
          @click="draft = q"
        >{{ q }}</button>
      </div>

      <form class="flex items-end gap-2" @submit.prevent="send">
        <button type="button" class="toolbar-btn shrink-0" aria-label="Attach a file">
          <PhPaperclip :size="18" />
        </button>
        <button type="button" class="toolbar-btn shrink-0" aria-label="Attach an image">
          <PhImage :size="18" />
        </button>
        <textarea
          v-model="draft" rows="1" placeholder="Write a message…"
          aria-label="Message"
          class="field max-h-32 min-h-[44px] flex-1 resize-none py-3"
          @keydown.enter.exact.prevent="send"
        />
        <UiButton type="submit" size="md" :disabled="!draft.trim()" class="shrink-0 !px-3">
          <PhPaperPlaneTilt :size="18" />
          <span class="sr-only">Send</span>
        </UiButton>
      </form>
    </div>
  </div>
</template>
