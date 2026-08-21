<script setup lang="ts">
import type { AuditEntry } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'

useHead({ title: 'Audit log' })

const { useAdminList } = useAdminApi()
const { formatDateTime, formatRelative } = useFormatters()
const { items: entries, pending } = useAdminList<AuditEntry>('admin-audit', '/admin/audit')

const r = useResource<AuditEntry>(entries, {
  searchFields: ['actorName', 'action', 'target', 'detail'],
  defaultSort: 'at',
  defaultDir: 'desc',
  perPage: 15,
})

const actorOptions = computed(() => [
  { value: 'all', label: 'Everyone' },
  ...[...new Set(entries.value.map((e) => e.actorName))].map((n) => ({ value: n, label: n })),
])

const columns: Column<AuditEntry>[] = [
  { key: 'action', label: 'Action', sortable: true },
  { key: 'actorName', label: 'By', sortable: true },
  { key: 'at', label: 'When', sortable: true },
]
</script>

<template>
  <SettingsShell title="Audit log" description="Who changed what, and when.">
    <UiPermissionGate capability="audit.view">
      <div class="admin-stack">
        <UiToolbar
          v-model:search="r.search.value" :active-filter-count="r.activeFilterCount.value"
          placeholder="Search actions…" @clear-filters="r.clearFilters()"
        >
          <template #actions>
            <UiSelect
              :model-value="r.filters.value.actorName ?? 'all'" :options="actorOptions" class="w-48"
              @update:model-value="r.setFilter('actorName', $event)"
            />
          </template>
        </UiToolbar>

        <UiDataTable
          :columns="columns" :rows="r.paged.value" :loading="pending"
          :sort="r.sort.value" :dir="r.dir.value"
          empty-title="Nothing logged"
          @sort="r.toggleSort"
        >
          <template #cell-action="{ row }">
            <span class="block text-fg-strong">{{ row.action }}</span>
            <span class="block truncate text-meta text-fg-muted">{{ row.target }}</span>
            <span class="block truncate text-meta text-fg-faint">{{ row.detail }}</span>
          </template>
          <template #cell-actorName="{ row }">
            <span class="flex items-center gap-2">
              <UiAvatar :name="row.actorName" :size="24" />
              <span class="min-w-0">
                <span class="block truncate text-fg">{{ row.actorName }}</span>
                <span class="block truncate text-meta text-fg-faint">{{ row.actorRole }}</span>
              </span>
            </span>
          </template>
          <template #cell-at="{ row }">
            <span class="text-fg-muted">{{ formatRelative(row.at) }}</span>
            <span class="block text-meta text-fg-faint">{{ formatDateTime(row.at) }}</span>
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
    </UiPermissionGate>
  </SettingsShell>
</template>
