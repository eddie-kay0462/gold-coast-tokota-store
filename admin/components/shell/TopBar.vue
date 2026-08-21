<script setup lang="ts">
import {
  PhBell, PhCaretRight, PhList, PhMagnifyingGlass, PhSidebarSimple, PhStar,
} from '@phosphor-icons/vue'
import { breadcrumbFor } from '~/utils/navigation'

/**
 * Header — 68px, per every Figma frame. Left: sidebar toggle, favourite star,
 * breadcrumb. Centre: search with a ⌘/ hint. Right: theme toggle, notification
 * bell, right-rail toggle.
 *
 * The one addition to the frame is the demo-data chip. It is not decoration:
 * most of this dashboard is reading bundled fixtures because the admin API
 * does not exist yet, and a dashboard that shows invented numbers without
 * saying so is actively misleading.
 */
defineProps<{ collapsed: boolean; railOpen: boolean; unread?: number }>()
const emit = defineEmits<{
  (e: 'toggle-sidebar'): void
  (e: 'toggle-mobile-nav'): void
  (e: 'toggle-rail'): void
  (e: 'open-search'): void
}>()

const route = useRoute()
const crumbs = computed(() => breadcrumbFor(route.path))
const { isDemoData } = useAdminApi()

const starred = useState<Set<string>>('starred-routes', () => new Set())
const isStarred = computed(() => starred.value.has(route.path))
function toggleStar() {
  const next = new Set(starred.value)
  next.has(route.path) ? next.delete(route.path) : next.add(route.path)
  starred.value = next
}
</script>

<template>
  <header
    class="sticky top-0 z-header flex h-header items-center gap-2 border-b border-border
           bg-bg-elevated px-3 md:px-4"
  >
    <!-- Mobile: opens the drawer. Desktop: collapses to the icon rail. -->
    <button type="button" class="toolbar-btn lg:hidden" aria-label="Open navigation" @click="emit('toggle-mobile-nav')">
      <PhList :size="20" />
    </button>
    <button
      type="button" class="toolbar-btn hidden lg:inline-flex"
      :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
      @click="emit('toggle-sidebar')"
    >
      <PhSidebarSimple :size="20" />
    </button>

    <button
      type="button" class="toolbar-btn hidden sm:inline-flex"
      :aria-label="isStarred ? 'Remove from favourites' : 'Add to favourites'"
      :aria-pressed="isStarred"
      @click="toggleStar"
    >
      <PhStar :size="20" :weight="isStarred ? 'fill' : 'regular'" :class="isStarred && 'text-accent'" />
    </button>

    <nav aria-label="Breadcrumb" class="ml-1 hidden min-w-0 items-center gap-1.5 md:flex">
      <template v-for="(crumb, i) in crumbs" :key="i">
        <PhCaretRight v-if="i > 0" :size="12" class="shrink-0 text-fg-faint" />
        <NuxtLink
          v-if="crumb.to" :to="crumb.to"
          class="truncate text-ui text-fg-muted transition-colors hover:text-fg-strong"
        >{{ crumb.label }}</NuxtLink>
        <span
          v-else class="truncate text-ui"
          :class="i === crumbs.length - 1 ? 'font-medium text-fg-strong' : 'text-fg-muted'"
        >{{ crumb.label }}</span>
      </template>
    </nav>

    <div class="ml-auto flex items-center gap-1.5">
      <UiBadge v-if="isDemoData" tone="warning" size="sm" dot class="hidden sm:inline-flex">
        <span class="hidden md:inline">Demo data</span>
        <span class="md:hidden">Demo</span>
      </UiBadge>

      <button
        type="button"
        class="hidden h-9 items-center gap-2 rounded-lg bg-bg-sunken px-3 text-ui text-fg-faint
               transition-colors hover:text-fg-muted md:flex md:w-56 lg:w-72"
        @click="emit('open-search')"
      >
        <PhMagnifyingGlass :size="16" />
        <span class="flex-1 text-left">Search</span>
        <kbd class="rounded-sm border border-border px-1 text-micro text-fg-faint">⌘/</kbd>
      </button>
      <button type="button" class="toolbar-btn md:hidden" aria-label="Search" @click="emit('open-search')">
        <PhMagnifyingGlass :size="20" />
      </button>

      <ShellThemeToggle />

      <button type="button" class="toolbar-btn relative" aria-label="Notifications" @click="emit('toggle-rail')">
        <PhBell :size="20" />
        <span
          v-if="unread" class="absolute right-1.5 top-1.5 size-2 rounded-pill bg-accent ring-2 ring-bg-elevated"
          :aria-label="`${unread} unread`"
        />
      </button>
    </div>
  </header>
</template>
