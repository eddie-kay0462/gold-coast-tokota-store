<script setup lang="ts">
import { PhFileText, PhScales } from '@phosphor-icons/vue'
import type { CmsPage } from '~/types'

/**
 * CMS pages.
 *
 * README names only `about`. The brand PDF specifies four more — Shipping &
 * Delivery, Returns & Exchanges, Privacy, Terms of Service — which the
 * storefront needs and which the owner must be able to change without a
 * deploy. They are all the same `Page` resource, so they live together, with
 * the legally load-bearing ones marked.
 */
useHead({ title: 'Pages' })

const { useAdminList } = useAdminApi()
const { formatRelative } = useFormatters()
const { items: cmsPages, pending } = useAdminList<CmsPage>('admin-pages', '/admin/pages')

const marketing = computed(() => cmsPages.value.filter((p) => !p.isPolicy))
const policies = computed(() => cmsPages.value.filter((p) => p.isPolicy))
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader
      title="Pages"
      description="Editable site content. Changes appear on the storefront immediately, with no deploy."
    />

    <div v-if="pending" class="grid gap-4 md:grid-cols-2">
      <div v-for="i in 4" :key="i" class="h-28 animate-pulse rounded-lg bg-bg-sunken" />
    </div>

    <template v-else>
      <section>
        <h2 class="text-meta uppercase tracking-wide text-fg-faint">Marketing</h2>
        <div class="mt-2 grid gap-4 md:grid-cols-2">
          <NuxtLink
            v-for="p in marketing" :key="p.id" :to="`/pages/${p.slug}`"
            class="card card-pad flex items-start gap-3 transition-colors hover:border-border-strong"
          >
            <span class="flex size-9 shrink-0 items-center justify-center rounded bg-bg-sunken text-fg-faint">
              <PhFileText :size="18" />
            </span>
            <span class="min-w-0">
              <span class="block truncate text-ui font-medium text-fg-strong">{{ p.title }}</span>
              <span class="block truncate font-mono text-meta text-fg-faint">/{{ p.slug }}</span>
              <span class="mt-1 block text-meta text-fg-muted">
                Updated {{ formatRelative(p.updatedAt) }}
                <span v-if="p.updatedByAdminName">by {{ p.updatedByAdminName }}</span>
              </span>
            </span>
          </NuxtLink>
        </div>
      </section>

      <section>
        <h2 class="text-meta uppercase tracking-wide text-fg-faint">Policies</h2>
        <p class="mt-1 text-ui text-fg-muted">
          These are the terms customers are held to. Check wording with the business before
          changing them.
        </p>
        <div class="mt-2 grid gap-4 md:grid-cols-2">
          <NuxtLink
            v-for="p in policies" :key="p.id" :to="`/pages/${p.slug}`"
            class="card card-pad flex items-start gap-3 transition-colors hover:border-border-strong"
          >
            <span class="flex size-9 shrink-0 items-center justify-center rounded bg-accent-soft text-accent-text">
              <PhScales :size="18" />
            </span>
            <span class="min-w-0">
              <span class="block truncate text-ui font-medium text-fg-strong">{{ p.title }}</span>
              <span class="block truncate font-mono text-meta text-fg-faint">/{{ p.slug }}</span>
              <span class="mt-1 block text-meta text-fg-muted">
                Updated {{ formatRelative(p.updatedAt) }}
                <span v-if="p.updatedByAdminName">by {{ p.updatedByAdminName }}</span>
              </span>
            </span>
          </NuxtLink>
        </div>
      </section>
    </template>
  </div>
</template>
