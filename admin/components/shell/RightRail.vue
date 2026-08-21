<script setup lang="ts">
import { PhBell, PhPulse, PhUserPlus, PhX } from '@phosphor-icons/vue'
import { activityFeed, customers, NOW } from '~/fixtures'
import { formatRelative } from '~/utils/formatters'

/**
 * Right rail — Figma 1:24956 (Dashboard frame): a 72px icon rail that opens a
 * 280px panel carrying Notifications, Activity and New Customers.
 *
 * Below `xl` there isn't room for both content and a rail, so it becomes a
 * sheet opened from the header bell instead of being dropped entirely — the
 * information is the same either way.
 */
const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ (e: 'update:open', v: boolean): void }>()

type Tab = 'notifications' | 'activity' | 'customers'
const tab = ref<Tab>('notifications')

const tabs: { key: Tab; icon: unknown; label: string }[] = [
  { key: 'notifications', icon: PhBell, label: 'Notifications' },
  { key: 'activity', icon: PhPulse, label: 'Activity' },
  { key: 'customers', icon: PhUserPlus, label: 'New customers' },
]

function select(next: Tab) {
  if (props.open && tab.value === next) emit('update:open', false)
  else { tab.value = next; emit('update:open', true) }
}

const notifications = computed(() => activityFeed.filter((a) => a.kind !== 'customer').slice(0, 6))
const activity = computed(() => activityFeed)
const newCustomers = computed(() =>
  [...customers]
    .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
    .slice(0, 8),
)

const when = (iso: string) => formatRelative(iso, NOW)

// Esc closes the sheet on small screens, where it overlays content.
onMounted(() => {
  const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape' && props.open) emit('update:open', false) }
  window.addEventListener('keydown', onKey)
  onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
})
</script>

<template>
  <!-- Scrim below xl, where the panel overlays rather than sits beside -->
  <div
    v-if="open"
    class="fixed inset-0 z-scrim bg-ink/40 xl:hidden"
    @click="emit('update:open', false)"
  />

  <aside
    class="fixed inset-y-0 right-0 z-drawer flex border-l border-border bg-bg-elevated
           transition-transform xl:sticky xl:top-0 xl:z-rail xl:h-dvh xl:translate-x-0"
    :class="open ? 'translate-x-0' : 'translate-x-full'"
    aria-label="Activity panel"
  >
    <!-- Panel -->
    <div v-if="open" class="flex w-[280px] flex-col overflow-hidden">
      <div class="flex h-header shrink-0 items-center justify-between border-b border-border px-4">
        <h2 class="text-section font-medium text-fg-strong">
          {{ tabs.find((t) => t.key === tab)?.label }}
        </h2>
        <button type="button" class="toolbar-btn -mr-2" aria-label="Close panel" @click="emit('update:open', false)">
          <PhX :size="18" />
        </button>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto p-4">
        <ul v-if="tab === 'notifications'" class="flex flex-col gap-4">
          <li v-for="n in notifications" :key="n.id" class="flex gap-3">
            <span class="mt-1.5 size-1.5 shrink-0 rounded-pill bg-accent" />
            <span class="min-w-0">
              <span class="block text-ui text-fg">{{ n.title }}</span>
              <span class="mt-0.5 block text-meta text-fg-faint">{{ when(n.at) }}</span>
            </span>
          </li>
        </ul>

        <ul v-else-if="tab === 'activity'" class="flex flex-col gap-4">
          <li v-for="a in activity" :key="a.id" class="flex gap-3">
            <UiAvatar v-if="a.actor" :name="a.actor" :src="a.avatar" :size="28" />
            <span v-else class="mt-1.5 size-1.5 shrink-0 rounded-pill bg-border-strong" />
            <span class="min-w-0">
              <span class="block text-ui text-fg">{{ a.title }}</span>
              <span class="mt-0.5 block text-meta text-fg-faint">
                {{ a.actor ? `${a.actor} · ` : '' }}{{ when(a.at) }}
              </span>
            </span>
          </li>
        </ul>

        <ul v-else class="flex flex-col gap-2">
          <li v-for="c in newCustomers" :key="c.id">
            <NuxtLink
              :to="`/customers/${c.id}`"
              class="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-bg-sunken"
            >
              <UiAvatar :name="c.name" :size="32" />
              <span class="min-w-0 flex-1">
                <span class="block truncate text-ui text-fg-strong">{{ c.name }}</span>
                <span class="block truncate text-meta text-fg-faint">{{ c.country }}</span>
              </span>
            </NuxtLink>
          </li>
        </ul>
      </div>
    </div>

    <!-- 72px icon rail, xl and up -->
    <div class="hidden w-rail shrink-0 flex-col items-center gap-1 border-l border-border py-4 xl:flex">
      <button
        v-for="t in tabs" :key="t.key"
        type="button" class="toolbar-btn"
        :class="open && tab === t.key && 'bg-bg-sunken text-fg-strong'"
        :aria-label="t.label" :title="t.label" :aria-pressed="open && tab === t.key"
        @click="select(t.key)"
      >
        <component :is="t.icon" :size="20" />
      </button>
    </div>
  </aside>
</template>
