<script setup lang="ts">
import * as PhosphorIcons from '@phosphor-icons/vue'
import { PhCaretRight } from '@phosphor-icons/vue'
import { NAVIGATION, type NavGroup, type NavItem } from '~/utils/navigation'
import type { Capability } from '~/utils/permissions'

/**
 * Left sidebar — Figma node 10:2521.
 *
 * Metrics transcribed from the frame: 212px wide, 16px padding, 8px item
 * padding at 12px radius, 20px icons, 16px chevrons, 50px sub-item indent,
 * 4px between items and 16px between groups.
 *
 * Two states beyond the frame, both required by the brief's responsive scope:
 * a 68px icon rail (the Calendar frame shows this collapsed form), and an
 * off-canvas drawer below `lg` where a fixed sidebar would eat the viewport.
 */
const props = defineProps<{
  collapsed: boolean
  /** Drawer visibility on small screens. */
  mobileOpen: boolean
  badges?: Record<string, number>
}>()

const emit = defineEmits<{
  (e: 'update:collapsed', v: boolean): void
  (e: 'update:mobileOpen', v: boolean): void
}>()

const route = useRoute()
const { can, user, role } = useAuth()

const icon = (name: string) =>
  (PhosphorIcons as unknown as Record<string, unknown>)[name] ?? PhosphorIcons.PhCircle

const allowed = (capability?: Capability) => !capability || can(capability)

/** Groups and items the current role can actually reach. */
const groups = computed<NavGroup[]>(() =>
  NAVIGATION
    .map((g) => ({
      ...g,
      items: g.items
        .filter((i) => allowed(i.capability))
        .map((i) => ({ ...i, children: i.children?.filter((c) => allowed(c.capability)) }))
        // A parent whose children are all gated away has nothing to open.
        .filter((i) => i.to || (i.children && i.children.length > 0)),
    }))
    .filter((g) => g.items.length > 0),
)

const isActive = (to?: string) =>
  !!to && (to === '/' ? route.path === '/' : route.path === to || route.path.startsWith(`${to}/`))

const itemActive = (item: NavItem) =>
  isActive(item.to) || !!item.children?.some((c) => isActive(c.to))

// Expansion is remembered across navigation, and any group containing the
// current route starts open so a deep link never lands in a collapsed tree.
const expanded = ref<Set<string>>(new Set())
watchEffect(() => {
  for (const g of groups.value) {
    for (const i of g.items) if (itemActive(i) && i.children) expanded.value.add(i.label)
  }
})

function toggle(item: NavItem) {
  const next = new Set(expanded.value)
  next.has(item.label) ? next.delete(item.label) : next.add(item.label)
  expanded.value = next
}

const badgeFor = (item: NavItem) =>
  item.badgeKey ? (props.badges?.[item.badgeKey] ?? 0) : 0

// Navigating on a phone should close the drawer; leaving it open hides the
// page the user just asked for.
watch(() => route.fullPath, () => emit('update:mobileOpen', false))

const profileOpen = ref(false)

const roleLabel = computed(
  () => ({ super_admin: 'Super Admin', admin: 'Admin', staff: 'Staff', intern: 'Intern' })[role.value],
)
</script>

<template>
  <!-- Scrim, mobile only -->
  <Transition
    enter-active-class="transition-opacity" leave-active-class="transition-opacity"
    enter-from-class="opacity-0" leave-to-class="opacity-0"
  >
    <div
      v-if="mobileOpen"
      class="fixed inset-0 z-scrim bg-ink/40 lg:hidden"
      @click="emit('update:mobileOpen', false)"
    />
  </Transition>

  <aside
    class="fixed inset-y-0 left-0 z-sidebar flex flex-col justify-between border-r border-border
           bg-bg-elevated p-4 transition-[width,transform] lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0"
    :class="[
      collapsed ? 'w-sidebar-collapsed' : 'w-sidebar',
      mobileOpen ? 'translate-x-0' : '-translate-x-full',
    ]"
    aria-label="Main navigation"
  >
    <div class="flex min-h-0 flex-1 flex-col gap-4">
      <!-- User badge (Figma 10:3108) — opens the profile modal -->
      <button
        type="button"
        class="flex items-center gap-2 rounded p-2 text-left transition-colors hover:bg-bg-sunken"
        :class="collapsed && 'justify-center px-0'"
        :aria-label="`Open your account — ${user?.name ?? ''}`"
        @click="profileOpen = true"
      >
        <UiAvatar :name="user?.name ?? ''" :src="user?.avatar" :size="32" />
        <span v-if="!collapsed" class="min-w-0 flex-1">
          <span class="block truncate text-ui text-fg-strong">{{ user?.name }}</span>
          <span class="block truncate text-micro uppercase tracking-wide text-fg-faint">
            {{ roleLabel }}
          </span>
        </span>
      </button>
      <ShellProfileModal v-model:open="profileOpen" />

      <nav class="-mx-1 min-h-0 flex-1 overflow-y-auto px-1">
        <div v-for="group in groups" :key="group.label" class="pb-3">
          <p v-if="!collapsed" class="rail-group-label">{{ group.label }}</p>
          <div v-else class="my-2 border-t border-border" />

          <ul class="flex flex-col gap-1">
            <li v-for="item in group.items" :key="item.label">
              <!-- Leaf -->
              <UiTooltip v-if="item.to && collapsed" :label="item.label" class="w-full">
                <NuxtLink
                  :to="item.to"
                  class="rail-item w-full justify-center px-0"
                  :class="isActive(item.to) && 'rail-item-active'"
                  :aria-label="item.label"
                >
                  <component :is="icon(item.icon)" :size="20" weight="regular" class="shrink-0" />
                  <span
                    v-if="badgeFor(item)"
                    class="absolute right-1.5 top-1.5 size-1.5 rounded-pill bg-accent"
                    :aria-label="`${badgeFor(item)} unread`"
                  />
                </NuxtLink>
              </UiTooltip>

              <NuxtLink
                v-else-if="item.to"
                :to="item.to"
                class="rail-item"
                :class="isActive(item.to) && 'rail-item-active'"
              >
                <component :is="icon(item.icon)" :size="20" weight="regular" class="shrink-0" />
                <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                <UiBadge v-if="badgeFor(item)" tone="accent" size="sm">{{ badgeFor(item) }}</UiBadge>
              </NuxtLink>

              <!-- Parent with children -->
              <template v-else>
                <UiTooltip v-if="collapsed" :label="item.label" class="w-full">
                  <button
                    type="button"
                    class="rail-item w-full justify-center px-0"
                    :class="itemActive(item) && 'rail-item-active'"
                    :aria-label="`${item.label} — expand sidebar to open`"
                    @click="emit('update:collapsed', false)"
                  >
                    <component :is="icon(item.icon)" :size="20" weight="regular" class="shrink-0" />
                    <span
                      v-if="badgeFor(item)"
                      class="absolute right-1.5 top-1.5 size-1.5 rounded-pill bg-accent"
                    />
                  </button>
                </UiTooltip>

                <button
                  v-else
                  type="button"
                  class="rail-item w-full"
                  :class="itemActive(item) && 'rail-item-active'"
                  :aria-expanded="expanded.has(item.label)"
                  @click="toggle(item)"
                >
                  <component :is="icon(item.icon)" :size="20" weight="regular" class="shrink-0" />
                  <span class="min-w-0 flex-1 truncate text-left">{{ item.label }}</span>
                  <UiBadge v-if="badgeFor(item)" tone="accent" size="sm">{{ badgeFor(item) }}</UiBadge>
                  <PhCaretRight
                    :size="14"
                    class="shrink-0 text-fg-faint transition-transform"
                    :class="expanded.has(item.label) && 'rotate-90'"
                  />
                </button>

                <ul v-if="!collapsed && expanded.has(item.label)" class="mt-0.5 flex flex-col">
                  <li v-for="child in item.children" :key="child.to">
                    <!-- Sub-item: 50px indent, 16px dot, per Figma 10:3079 -->
                    <NuxtLink
                      :to="child.to"
                      class="flex min-h-[36px] items-center gap-2 rounded py-2 pl-[38px] pr-3 text-ui
                             text-fg-muted transition-colors hover:text-fg-strong"
                      :class="isActive(child.to) && 'font-medium text-fg-strong'"
                    >
                      <span
                        class="size-1.5 shrink-0 rounded-pill transition-colors"
                        :class="isActive(child.to) ? 'bg-accent' : 'bg-border-strong'"
                      />
                      <span class="truncate">{{ child.label }}</span>
                    </NuxtLink>
                  </li>
                </ul>
              </template>
            </li>
          </ul>
        </div>
      </nav>
    </div>

    <!-- Brand lockup, replacing the kit's "LogicLab" footer -->
    <div class="flex items-center gap-2 pt-3">
      <img
        src="/brand/logo-mark.png"
        alt=""
        class="size-5 shrink-0 object-contain dark:hidden"
      >
      <img
        src="/brand/logo-mark-white.png"
        alt=""
        class="hidden size-5 shrink-0 object-contain dark:block"
      >
      <span v-if="!collapsed" class="truncate text-meta text-fg-faint">Gold Coast Tokota</span>
    </div>
  </aside>
</template>
