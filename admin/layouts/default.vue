<script setup lang="ts">
import { dashboardMetrics } from '~/fixtures'

/**
 * Admin shell: sidebar · header · content · right rail, per the Figma frames.
 *
 * Layout strategy by breakpoint:
 *   < lg   sidebar is an off-canvas drawer; rail is a sheet
 *   lg–xl  sidebar is sticky; rail is still a sheet (no room for both)
 *   ≥ xl   all three columns sit side by side, as drawn
 */
const collapsed = useCookie<boolean>('gct-admin-sidebar-collapsed', {
  default: () => false, maxAge: 60 * 60 * 24 * 365, sameSite: 'lax', path: '/',
})
const mobileNavOpen = ref(false)
const railOpen = ref(false)
const searchOpen = ref(false)

// Counts shown against nav items, so the sidebar carries a little operational
// signal rather than being purely navigational.
//
// Unread messages are live: the inbox page keeps this shared count current,
// and between visits it is refreshed every 30 s for anyone who can see the
// inbox. The sample figure shows only until a live answer has arrived.
const { adminFetch } = useAdminApi()
const { can } = useAuth()
const unreadMessages = useState<number | null>('inbox-unread', () => null)

usePolling(async () => {
  if (!can('inbox.view')) return
  const res = await adminFetch<unknown[]>('/admin/inbox/threads', { query: { unread: 1 } })
  if (res.source === 'live') unreadMessages.value = Number(res.meta?.unreadTotal ?? 0)
}, 30_000)

onMounted(async () => {
  if (!can('inbox.view')) return
  try {
    const res = await adminFetch<unknown[]>('/admin/inbox/threads', { query: { unread: 1 } })
    if (res.source === 'live') unreadMessages.value = Number(res.meta?.unreadTotal ?? 0)
  } catch {
    // The badge is a convenience; the inbox itself reports any problem.
  }
})

const badges = computed(() => ({
  unreadMessages: unreadMessages.value ?? dashboardMetrics.unreadMessages,
  pendingBookings: dashboardMetrics.pendingBookings,
  openOrders: dashboardMetrics.openReturns,
}))

function onKeydown(e: KeyboardEvent) {
  const isPaletteKey = (e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === '/')
  if (isPaletteKey) { e.preventDefault(); searchOpen.value = !searchOpen.value }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

// The rail opens by default only where there is genuinely room for it.
onMounted(() => { railOpen.value = window.innerWidth >= 1536 })
</script>

<template>
  <div class="flex min-h-dvh bg-bg">
    <ShellSidebarNav
      v-model:collapsed="collapsed"
      v-model:mobile-open="mobileNavOpen"
      :badges="badges"
    />

    <div class="flex min-w-0 flex-1 flex-col">
      <ShellTopBar
        :collapsed="collapsed"
        :rail-open="railOpen"
        :unread="badges.unreadMessages"
        @toggle-sidebar="collapsed = !collapsed"
        @toggle-mobile-nav="mobileNavOpen = !mobileNavOpen"
        @toggle-rail="railOpen = !railOpen"
        @open-search="searchOpen = true"
      />

      <ShellAccessBanner />

      <main class="min-w-0 flex-1 admin-gutter py-4 md:py-5">
        <slot />
      </main>
    </div>

    <ShellRightRail v-model:open="railOpen" />
    <ShellCommandPalette v-model:open="searchOpen" />
    <UiToastRegion />
  </div>
</template>
