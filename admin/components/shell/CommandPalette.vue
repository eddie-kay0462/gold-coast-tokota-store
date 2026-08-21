<script setup lang="ts">
import { PhMagnifyingGlass, PhArrowRight } from '@phosphor-icons/vue'
import { NAVIGATION } from '~/utils/navigation'
import type { Capability } from '~/utils/permissions'

/** ⌘K / ⌘/ jump-to-page. Only lists destinations the current role can open. */
const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ (e: 'update:open', v: boolean): void }>()

const { can } = useAuth()
const query = ref('')
const activeIndex = ref(0)
const inputEl = ref<HTMLInputElement | null>(null)

const allowed = (c?: Capability) => !c || can(c)

interface Entry { label: string; group: string; to: string }

const entries = computed<Entry[]>(() =>
  NAVIGATION.flatMap((g) =>
    g.items.flatMap((i) => {
      if (!allowed(i.capability)) return []
      const rows: Entry[] = i.to ? [{ label: i.label, group: g.label, to: i.to }] : []
      for (const c of i.children ?? []) {
        if (allowed(c.capability)) rows.push({ label: `${i.label} · ${c.label}`, group: g.label, to: c.to })
      }
      return rows
    }),
  ),
)

const results = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return entries.value.slice(0, 8)
  return entries.value.filter((e) => e.label.toLowerCase().includes(q)).slice(0, 10)
})

watch(results, () => { activeIndex.value = 0 })
watch(() => props.open, async (open) => {
  if (!open) return
  query.value = ''
  await nextTick()
  inputEl.value?.focus()
})

function go(entry: Entry) {
  emit('update:open', false)
  navigateTo(entry.to)
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'ArrowDown') { e.preventDefault(); activeIndex.value = (activeIndex.value + 1) % results.value.length }
  else if (e.key === 'ArrowUp') { e.preventDefault(); activeIndex.value = (activeIndex.value - 1 + results.value.length) % results.value.length }
  else if (e.key === 'Enter') { const hit = results.value[activeIndex.value]; if (hit) go(hit) }
  else if (e.key === 'Escape') emit('update:open', false)
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity" leave-active-class="transition-opacity"
      enter-from-class="opacity-0" leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-modal flex items-start justify-center bg-ink/40 p-4 pt-[12vh]"
        @click.self="emit('update:open', false)"
      >
        <div
          class="w-full max-w-lg overflow-hidden rounded-lg bg-bg-elevated shadow-overlay"
          role="dialog" aria-modal="true" aria-label="Command palette"
        >
          <div class="flex items-center gap-3 border-b border-border px-4">
            <PhMagnifyingGlass :size="18" class="shrink-0 text-fg-faint" />
            <input
              ref="inputEl" v-model="query" type="text" placeholder="Jump to…"
              class="h-12 flex-1 bg-transparent text-ui text-fg outline-none placeholder:text-fg-faint"
              @keydown="onKeydown"
            >
            <kbd class="rounded-sm border border-border px-1 text-micro text-fg-faint">Esc</kbd>
          </div>

          <ul v-if="results.length" class="max-h-80 overflow-y-auto p-2">
            <li v-for="(r, i) in results" :key="r.to">
              <button
                type="button"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition-colors"
                :class="i === activeIndex ? 'bg-bg-sunken' : 'hover:bg-bg-sunken'"
                @click="go(r)" @mouseenter="activeIndex = i"
              >
                <span class="min-w-0 flex-1 truncate text-ui text-fg-strong">{{ r.label }}</span>
                <span class="shrink-0 text-meta text-fg-faint">{{ r.group }}</span>
                <PhArrowRight :size="14" class="shrink-0 text-fg-faint" />
              </button>
            </li>
          </ul>
          <p v-else class="px-4 py-8 text-center text-ui text-fg-muted">
            Nothing matches “{{ query }}”.
          </p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
