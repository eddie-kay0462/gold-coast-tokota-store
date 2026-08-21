<script setup lang="ts">
import { PhPlus, PhTag } from '@phosphor-icons/vue'
import type { Category } from '~/types'

useHead({ title: 'Categories' })

const { useAdminList } = useAdminApi()
const { items: categories, pending } = useAdminList<Category>('admin-categories', '/admin/categories')
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Categories" description="How the catalogue is grouped on the storefront.">
      <template #actions>
        <UiPermissionGate capability="products.write" quiet>
          <UiButton size="sm"><PhPlus :size="16" />New category</UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <div v-if="pending" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="i in 5" :key="i" class="h-24 animate-pulse rounded-lg bg-bg-sunken" />
    </div>

    <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="c in categories" :key="c.id">
        <NuxtLink
          :to="`/products?category=${c.slug}`"
          class="card card-pad flex items-start gap-3 transition-colors hover:border-border-strong"
        >
          <span class="flex size-9 shrink-0 items-center justify-center rounded bg-bg-sunken text-fg-faint">
            <PhTag :size="18" />
          </span>
          <span class="min-w-0">
            <span class="block truncate text-ui font-medium text-fg-strong">{{ c.name }}</span>
            <span class="block truncate font-mono text-meta text-fg-faint">/{{ c.slug }}</span>
            <span class="mt-1 block text-meta text-fg-muted">
              {{ c.productCount }} product{{ c.productCount === 1 ? '' : 's' }}
            </span>
          </span>
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
