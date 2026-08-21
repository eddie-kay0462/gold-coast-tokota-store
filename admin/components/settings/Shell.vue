<script setup lang="ts">
import type { Capability } from '~/utils/permissions'

/**
 * Shared chrome for every settings screen: a section nav plus the page body.
 * The nav is capability-filtered, so a Staff account never sees a Payments tab
 * that would 403 on open.
 */
defineProps<{ title: string; description?: string }>()

const { can } = useAuth()
const route = useRoute()

const sections: { label: string; to: string; capability?: Capability }[] = [
  { label: 'General', to: '/settings', capability: 'settings.view' },
  { label: 'Store & currency', to: '/settings/store', capability: 'settings.view' },
  { label: 'WhatsApp', to: '/settings/whatsapp', capability: 'settings.view' },
  { label: 'Payments', to: '/settings/payments', capability: 'settings.payments' },
  { label: 'Delivery', to: '/settings/delivery', capability: 'settings.view' },
  { label: 'Notifications', to: '/settings/notifications', capability: 'settings.view' },
  { label: 'SEO', to: '/settings/seo', capability: 'settings.view' },
  { label: 'Roles & access', to: '/settings/roles', capability: 'team.view' },
  { label: 'Audit log', to: '/settings/audit', capability: 'audit.view' },
]

const visible = computed(() => sections.filter((s) => !s.capability || can(s.capability)))
const isActive = (to: string) => route.path === to
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader :title="title" :description="description">
      <template v-if="$slots.actions" #actions><slot name="actions" /></template>
    </UiPageHeader>

    <div class="flex flex-col gap-5 lg:flex-row lg:gap-6">
      <!--
        Section nav: horizontal scroller on narrow, sidebar from lg.

        Deliberately NOT the storefront's full-bleed `-mx-gutter px-gutter`
        scroller pattern. That works when the scroller is a direct child of the
        gutter container; here it sits two levels deeper, so the negative
        margin escaped its parent's content box and the whole document gained
        16px of horizontal scroll at 390px. Caught by the responsive sweep.
        A compact pill row does not need to bleed to the edge anyway.
      -->
      <nav class="min-w-0 shrink-0 overflow-x-auto lg:w-52 lg:overflow-visible">
        <ul class="flex min-w-max gap-1 lg:min-w-0 lg:flex-col">
          <li v-for="s in visible" :key="s.to">
            <NuxtLink
              :to="s.to"
              class="block whitespace-nowrap rounded-lg px-3 py-2 text-ui transition-colors"
              :class="isActive(s.to)
                ? 'bg-bg-sunken font-medium text-fg-strong'
                : 'text-fg-muted hover:bg-bg-sunken hover:text-fg-strong'"
            >{{ s.label }}</NuxtLink>
          </li>
        </ul>
      </nav>

      <div class="min-w-0 flex-1"><slot /></div>
    </div>
  </div>
</template>
