<script setup lang="ts">
import { PhDownloadSimple } from '@phosphor-icons/vue'
import type { NewsletterSubscriber } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'

/**
 * Newsletter subscribers. Read-only with export, per README Feature 9.
 * Single opt-in means a row here is already active — there is no pending
 * state to manage, which is why there is no status column.
 */
useHead({ title: 'Newsletter' })

const { useAdminList } = useAdminApi()
const { formatDate, formatRelative } = useFormatters()
const { items: subs, pending } = useAdminList<NewsletterSubscriber>('admin-newsletter', '/admin/newsletter')

const r = useResource<NewsletterSubscriber>(subs, {
  searchFields: ['email', 'source'],
  defaultSort: 'subscribedAt',
  defaultDir: 'desc',
  perPage: 15,
})

const sourceOptions = computed(() => [
  { value: 'all', label: 'All sources' },
  ...[...new Set(subs.value.map((s) => s.source))].map((s) => ({ value: s, label: s })),
])

const columns: Column<NewsletterSubscriber>[] = [
  { key: 'email', label: 'Email', sortable: true },
  { key: 'source', label: 'Source', sortable: true },
  { key: 'subscribedAt', label: 'Subscribed', sortable: true },
]
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader
      title="Newsletter"
      :description="`${subs.length} subscribers. Single opt-in — a subscription is active the moment it is submitted.`"
    >
      <template #actions>
        <UiPermissionGate capability="customers.export" quiet>
          <UiButton variant="secondary" size="sm"><PhDownloadSimple :size="16" />Export CSV</UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <UiToolbar
      v-model:search="r.search.value" :active-filter-count="r.activeFilterCount.value"
      placeholder="Search by email or source…" @clear-filters="r.clearFilters()"
    >
      <template #actions>
        <UiSelect
          :model-value="r.filters.value.source ?? 'all'" :options="sourceOptions" class="w-48"
          @update:model-value="r.setFilter('source', $event)"
        />
      </template>
    </UiToolbar>

    <UiDataTable
      :columns="columns" :rows="r.paged.value" :loading="pending"
      :sort="r.sort.value" :dir="r.dir.value"
      empty-title="No subscribers match"
      @sort="r.toggleSort"
    >
      <template #cell-email="{ row }">
        <span class="text-fg-strong">{{ row.email }}</span>
      </template>
      <template #cell-source="{ row }">
        <UiBadge tone="outline" size="sm">{{ row.source }}</UiBadge>
      </template>
      <template #cell-subscribedAt="{ row }">
        <span class="text-fg-muted">{{ formatDate(row.subscribedAt) }}</span>
        <span class="block text-meta text-fg-faint">{{ formatRelative(row.subscribedAt) }}</span>
      </template>
      <template #footer>
        <UiPagination
          :page="r.page.value" :page-count="r.pageCount.value"
          :total="r.total.value" :per-page="r.perPage.value"
          @update:page="r.page.value = $event"
        />
      </template>
    </UiDataTable>
  </div>
</template>
