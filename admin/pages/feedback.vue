<script setup lang="ts">
import { PhStar } from '@phosphor-icons/vue'
import type { FeedbackEntry } from '~/types'

/**
 * Customer feedback. Presented as cards rather than a table — these are
 * paragraphs of prose, and a table row truncates the only thing worth reading.
 */
useHead({ title: 'Feedback' })

const { useAdminList } = useAdminApi()
const { formatRelative } = useFormatters()
const { items: entries, pending } = useAdminList<FeedbackEntry>('admin-feedback', '/admin/feedback')

const search = ref('')
const visible = computed(() => {
  const q = search.value.trim().toLowerCase()
  return [...entries.value]
    .filter((e) => !q || e.message.toLowerCase().includes(q) || e.name.toLowerCase().includes(q))
    .sort((a, b) => new Date(b.submittedAt).getTime() - new Date(a.submittedAt).getTime())
})

const rated = computed(() => entries.value.filter((e) => e.rating !== null))
const average = computed(() =>
  rated.value.length
    ? (rated.value.reduce((s, e) => s + (e.rating ?? 0), 0) / rated.value.length).toFixed(1)
    : '—',
)
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader
      title="Feedback"
      :description="`${entries.length} submissions, averaging ${average} out of 5 across ${rated.length} rated.`"
    />

    <UiToolbar v-model:search="search" placeholder="Search feedback…" />

    <div v-if="pending" class="grid gap-4 md:grid-cols-2">
      <div v-for="i in 4" :key="i" class="h-36 animate-pulse rounded-lg bg-bg-sunken" />
    </div>

    <div v-else-if="!visible.length" class="card">
      <UiEmptyState title="No feedback matches" />
    </div>

    <ul v-else class="grid gap-4 md:grid-cols-2">
      <li v-for="e in visible" :key="e.id" class="card card-pad flex flex-col">
        <div class="flex items-start justify-between gap-3">
          <div class="flex min-w-0 items-center gap-2.5">
            <UiAvatar :name="e.name" :size="32" />
            <span class="min-w-0">
              <span class="block truncate text-ui text-fg-strong">{{ e.name }}</span>
              <span class="block truncate text-meta text-fg-faint">{{ e.email }}</span>
            </span>
          </div>
          <span v-if="e.rating" class="flex shrink-0 gap-0.5" :aria-label="`${e.rating} out of 5`">
            <PhStar
              v-for="i in 5" :key="i" :size="14"
              :weight="i <= e.rating ? 'fill' : 'regular'"
              :class="i <= e.rating ? 'text-accent' : 'text-border-strong'"
            />
          </span>
        </div>

        <p class="mt-3 flex-1 text-ui leading-relaxed text-fg">{{ e.message }}</p>
        <p class="mt-3 text-meta text-fg-faint">{{ formatRelative(e.submittedAt) }}</p>
      </li>
    </ul>
  </div>
</template>
