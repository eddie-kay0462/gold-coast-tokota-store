<script setup lang="ts" generic="T extends Record<string, any>">
import { PhCaretDown, PhCaretUp, PhCaretUpDown } from '@phosphor-icons/vue'

/**
 * The one table in this app — Figma node 10:3761 (Products frame).
 *
 * Two renderings of the same data, chosen by breakpoint. A table below `md`
 * either overflows horizontally or squeezes columns to unreadable, so under
 * `md` each row becomes a card: primary column as the heading, the rest as
 * label/value pairs. Same slots drive both, so a page defines its columns once.
 */
export interface Column<Row> {
  key: string
  label: string
  sortable?: boolean
  /** Hidden in the mobile card view — use for redundant or decorative columns. */
  hideOnCard?: boolean
  align?: 'left' | 'right'
  width?: string
}

const props = withDefaults(defineProps<{
  columns: Column<T>[]
  rows: T[]
  /** Column whose value heads each mobile card. Defaults to the first column. */
  primaryKey?: string
  selectable?: boolean
  loading?: boolean
  sort?: string | null
  dir?: 'asc' | 'desc'
  rowLink?: (row: T) => string
  emptyTitle?: string
  emptyDescription?: string
}>(), {
  selectable: false, loading: false, sort: null, dir: 'asc',
  emptyTitle: 'Nothing here yet',
})

const emit = defineEmits<{
  (e: 'sort', key: string): void
  (e: 'toggle-row', row: T): void
  (e: 'toggle-all'): void
}>()

const selected = defineModel<Set<string>>('selected', { default: () => new Set<string>() })

const primary = computed(() => props.primaryKey ?? props.columns[0]?.key ?? 'id')
const rowKey = (row: T) => String(row.id ?? JSON.stringify(row))
const isSelected = (row: T) => selected.value.has(rowKey(row))
const allSelected = computed(() => props.rows.length > 0 && props.rows.every(isSelected))

const cardColumns = computed(() => props.columns.filter((c) => c.key !== primary.value && !c.hideOnCard))

function sortIcon(col: Column<T>) {
  if (props.sort !== col.key) return PhCaretUpDown
  return props.dir === 'asc' ? PhCaretUp : PhCaretDown
}
</script>

<template>
  <div class="card overflow-hidden">
    <!-- Loading -->
    <div v-if="loading" class="flex flex-col gap-px p-4">
      <div v-for="i in 6" :key="i" class="h-12 animate-pulse rounded bg-bg-sunken" />
    </div>

    <UiEmptyState
      v-else-if="!rows.length"
      :title="emptyTitle"
      :description="emptyDescription"
    >
      <slot name="empty-action" />
    </UiEmptyState>

    <template v-else>
      <!-- ── Table, md and up ── -->
      <div class="hidden overflow-x-auto md:block">
        <table class="w-full min-w-[640px] border-collapse">
          <thead class="table-head">
            <tr class="border-b border-border">
              <th v-if="selectable" class="w-10 px-4 py-3">
                <input
                  type="checkbox" :checked="allSelected" aria-label="Select all rows"
                  class="size-4 rounded-sm border-border-strong text-accent focus:ring-accent"
                  @change="emit('toggle-all')"
                >
              </th>
              <th
                v-for="col in columns" :key="col.key"
                class="px-4 py-3 font-medium"
                :class="col.align === 'right' && 'text-right'"
                :style="col.width ? { width: col.width } : undefined"
                :aria-sort="sort === col.key ? (dir === 'asc' ? 'ascending' : 'descending') : undefined"
              >
                <button
                  v-if="col.sortable"
                  type="button"
                  class="inline-flex items-center gap-1 transition-colors hover:text-fg-strong"
                  :class="[col.align === 'right' && 'flex-row-reverse', sort === col.key && 'text-fg-strong']"
                  @click="emit('sort', col.key)"
                >
                  {{ col.label }}
                  <component :is="sortIcon(col)" :size="12" />
                </button>
                <span v-else>{{ col.label }}</span>
              </th>
              <th v-if="$slots.actions" class="w-px px-4 py-3 text-right font-medium">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="row in rows" :key="rowKey(row)"
              class="table-row" :class="isSelected(row) && 'bg-accent-soft/40'"
            >
              <td v-if="selectable" class="px-4 py-3">
                <input
                  type="checkbox" :checked="isSelected(row)" :aria-label="`Select row ${rowKey(row)}`"
                  class="size-4 rounded-sm border-border-strong text-accent focus:ring-accent"
                  @change="emit('toggle-row', row)"
                >
              </td>
              <td
                v-for="col in columns" :key="col.key"
                class="table-cell" :class="col.align === 'right' && 'text-right'"
              >
                <NuxtLink
                  v-if="rowLink && col.key === primary" :to="rowLink(row)"
                  class="font-medium text-fg-strong underline-offset-4 hover:underline"
                >
                  <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">{{ row[col.key] }}</slot>
                </NuxtLink>
                <slot v-else :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                  {{ row[col.key] }}
                </slot>
              </td>
              <td v-if="$slots.actions" class="table-cell text-right">
                <div class="flex justify-end gap-0.5"><slot name="actions" :row="row" /></div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── Cards, below md ── -->
      <ul class="divide-y divide-border md:hidden">
        <li v-for="row in rows" :key="rowKey(row)" class="p-4" :class="isSelected(row) && 'bg-accent-soft/40'">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <NuxtLink v-if="rowLink" :to="rowLink(row)" class="block font-medium text-fg-strong">
                <slot :name="`cell-${primary}`" :row="row" :value="row[primary]">{{ row[primary] }}</slot>
              </NuxtLink>
              <div v-else class="font-medium text-fg-strong">
                <slot :name="`cell-${primary}`" :row="row" :value="row[primary]">{{ row[primary] }}</slot>
              </div>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 gap-0.5"><slot name="actions" :row="row" /></div>
          </div>

          <dl class="mt-2.5 grid grid-cols-2 gap-x-4 gap-y-2">
            <div v-for="col in cardColumns" :key="col.key" class="min-w-0">
              <dt class="text-micro uppercase tracking-wide text-fg-faint">{{ col.label }}</dt>
              <dd class="mt-0.5 truncate text-ui text-fg">
                <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">{{ row[col.key] }}</slot>
              </dd>
            </div>
          </dl>
        </li>
      </ul>

      <slot name="footer" />
    </template>
  </div>
</template>
