<script setup lang="ts">
import { PhMagnifyingGlass } from '@phosphor-icons/vue'
import type { ChatThread } from '~/types'

/** Conversation list — Figma node 29:8553. */
const props = defineProps<{ threads: ChatThread[]; activeId: string | null }>()
const emit = defineEmits<{ (e: 'select', id: string): void }>()

const search = ref('')
const { formatTime, formatRelative } = useFormatters()

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.threads
    .filter((t) => !q || t.contactName.toLowerCase().includes(q) || t.lastMessagePreview.toLowerCase().includes(q))
    .sort((a, b) => new Date(b.lastMessageAt).getTime() - new Date(a.lastMessageAt).getTime())
})

const topicTone = {
  order: 'info', booking: 'accent', diy_order: 'accent',
  returns: 'warning', wholesale: 'success', general: 'neutral',
} as const
</script>

<template>
  <div class="flex min-h-0 flex-col">
    <div class="shrink-0 border-b border-border p-3">
      <div class="relative">
        <PhMagnifyingGlass :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-fg-faint" />
        <input
          v-model="search" type="search" placeholder="Search by name…"
          aria-label="Search conversations"
          class="field min-h-[40px] rounded-pill pl-9"
        >
      </div>
    </div>

    <ul class="min-h-0 flex-1 overflow-y-auto p-2">
      <li v-for="t in filtered" :key="t.id">
        <button
          type="button"
          class="flex w-full items-start gap-3 rounded-lg p-2.5 text-left transition-colors"
          :class="t.id === activeId ? 'bg-bg-sunken' : 'hover:bg-bg-sunken'"
          @click="emit('select', t.id)"
        >
          <UiAvatar :name="t.contactName" :src="t.avatar" :size="40" :online="t.isOnline" />
          <span class="min-w-0 flex-1">
            <span class="flex items-baseline gap-2">
              <span class="min-w-0 flex-1 truncate text-ui font-medium text-fg-strong">
                {{ t.contactName }}
              </span>
              <span class="shrink-0 text-micro text-fg-faint">{{ formatTime(t.lastMessageAt) }}</span>
            </span>
            <span class="mt-0.5 flex items-center gap-2">
              <span class="min-w-0 flex-1 truncate text-meta text-fg-muted">
                {{ t.lastMessagePreview }}
              </span>
              <span
                v-if="t.unreadCount"
                class="flex size-4 shrink-0 items-center justify-center rounded-pill bg-accent text-micro font-medium text-accent-fg"
              >{{ t.unreadCount }}</span>
            </span>
            <UiBadge :tone="topicTone[t.topic]" size="sm" class="mt-1.5">
              {{ t.topic.replace('_', ' ') }}
            </UiBadge>
          </span>
        </button>
      </li>
      <li v-if="!filtered.length" class="px-3 py-8 text-center text-meta text-fg-faint">
        No conversations match.
      </li>
    </ul>
  </div>
</template>
