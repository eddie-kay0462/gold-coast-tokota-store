<script setup lang="ts">
import { PhCaretLeft, PhCaretRight } from '@phosphor-icons/vue'

const props = defineProps<{ page: number; pageCount: number; total: number; perPage: number }>()
const emit = defineEmits<{ (e: 'update:page', v: number): void }>()

const from = computed(() => (props.total === 0 ? 0 : (props.page - 1) * props.perPage + 1))
const to = computed(() => Math.min(props.page * props.perPage, props.total))

/** Window of page numbers around the current one, with ellipses. */
const pages = computed<(number | '…')[]>(() => {
  const n = props.pageCount
  if (n <= 7) return Array.from({ length: n }, (_, i) => i + 1)
  const p = props.page
  const out: (number | '…')[] = [1]
  if (p > 3) out.push('…')
  for (let i = Math.max(2, p - 1); i <= Math.min(n - 1, p + 1); i++) out.push(i)
  if (p < n - 2) out.push('…')
  out.push(n)
  return out
})
</script>

<template>
  <div v-if="total > 0" class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
    <p class="text-meta text-fg-muted">
      Showing <span class="text-fg-strong">{{ from }}–{{ to }}</span> of
      <span class="text-fg-strong">{{ total }}</span>
    </p>

    <div v-if="pageCount > 1" class="flex items-center gap-1">
      <button
        type="button" class="toolbar-btn" :disabled="page === 1" aria-label="Previous page"
        :class="page === 1 && 'pointer-events-none opacity-40'"
        @click="emit('update:page', page - 1)"
      >
        <PhCaretLeft :size="16" />
      </button>

      <template v-for="(p, i) in pages" :key="`${p}-${i}`">
        <span v-if="p === '…'" class="px-1 text-meta text-fg-faint">…</span>
        <button
          v-else type="button"
          class="h-9 min-w-9 rounded-lg px-2 text-meta transition-colors"
          :class="p === page
            ? 'bg-primary font-medium text-primary-fg'
            : 'text-fg-muted hover:bg-bg-sunken hover:text-fg-strong'"
          :aria-current="p === page ? 'page' : undefined"
          @click="emit('update:page', p as number)"
        >{{ p }}</button>
      </template>

      <button
        type="button" class="toolbar-btn" :disabled="page === pageCount" aria-label="Next page"
        :class="page === pageCount && 'pointer-events-none opacity-40'"
        @click="emit('update:page', page + 1)"
      >
        <PhCaretRight :size="16" />
      </button>
    </div>
  </div>
</template>
