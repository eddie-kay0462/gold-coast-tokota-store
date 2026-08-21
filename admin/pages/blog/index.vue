<script setup lang="ts">
import { PhPencilSimple, PhPlus } from '@phosphor-icons/vue'
import type { BlogPost } from '~/types'
import type { Column } from '~/components/ui/DataTable.vue'

useHead({ title: 'Blog' })

const { useAdminList } = useAdminApi()
const { formatDate, formatRelative } = useFormatters()
const { items: posts, pending } = useAdminList<BlogPost>('admin-blog', '/admin/blog')

const r = useResource<BlogPost>(posts, {
  searchFields: ['title', 'authorName', 'excerpt'],
  defaultSort: 'updatedAt',
  defaultDir: 'desc',
})

const statusOptions = [
  { value: 'all', label: 'All posts' },
  { value: 'true', label: 'Published' },
  { value: 'false', label: 'Drafts' },
]

const columns: Column<BlogPost>[] = [
  { key: 'title', label: 'Post', sortable: true },
  { key: 'authorName', label: 'Author', sortable: true },
  { key: 'isPublished', label: 'Status', sortable: true },
  { key: 'publishedAt', label: 'Published', sortable: true },
  { key: 'updatedAt', label: 'Updated', sortable: true, hideOnCard: true },
]
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Blog" description="Stories from the workshop. Published posts go straight to the storefront.">
      <template #actions>
        <UiPermissionGate capability="content.write" quiet>
          <UiButton size="sm" to="/blog/new"><PhPlus :size="16" />New post</UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <UiToolbar
      v-model:search="r.search.value" :active-filter-count="r.activeFilterCount.value"
      placeholder="Search by title, author or excerpt…" @clear-filters="r.clearFilters()"
    >
      <template #actions>
        <UiSelect
          :model-value="r.filters.value.isPublished ?? 'all'" :options="statusOptions" class="w-40"
          @update:model-value="r.setFilter('isPublished', $event)"
        />
      </template>
    </UiToolbar>

    <UiDataTable
      :columns="columns" :rows="r.paged.value" :loading="pending"
      :sort="r.sort.value" :dir="r.dir.value" :row-link="(p) => `/blog/${p.id}`"
      empty-title="No posts match"
      @sort="r.toggleSort"
    >
      <template #cell-title="{ row }">
        <span class="block truncate">{{ row.title }}</span>
        <span class="block truncate text-meta font-normal text-fg-faint">{{ row.excerpt }}</span>
      </template>
      <template #cell-authorName="{ row }">
        <span class="flex items-center gap-2">
          <UiAvatar :name="row.authorName" :size="24" />
          <span class="truncate text-fg-muted">{{ row.authorName }}</span>
        </span>
      </template>
      <template #cell-isPublished="{ row }">
        <UiBadge :tone="row.isPublished ? 'success' : 'neutral'" dot>
          {{ row.isPublished ? 'Published' : 'Draft' }}
        </UiBadge>
      </template>
      <template #cell-publishedAt="{ row }">
        <span class="text-fg-muted">{{ row.publishedAt ? formatDate(row.publishedAt) : '—' }}</span>
      </template>
      <template #cell-updatedAt="{ row }">
        <span class="text-fg-muted">{{ formatRelative(row.updatedAt) }}</span>
      </template>
      <template #actions="{ row }">
        <NuxtLink :to="`/blog/${row.id}`" class="toolbar-btn" :aria-label="`Edit ${row.title}`">
          <PhPencilSimple :size="16" />
        </NuxtLink>
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
