import type { ListQuery } from '~/types'

/**
 * Search / sort / filter / paginate / select over a collection.
 *
 * Every table page in the admin needs the same six pieces of state and the
 * same three derived values. Centralising them is what keeps each page file
 * short enough to read in one screen, and guarantees that "search" behaves
 * identically on Orders and on Newsletter.
 *
 * Filtering happens client-side against the loaded page. That is correct for
 * fixture-scale data; when the real API lands and datasets grow, pass the
 * reactive `query` into `useAdminList` instead and the server does the work —
 * the component API does not change.
 */
export interface ResourceOptions<T> {
  /** Fields searched by the free-text box. Omit to search every string field. */
  searchFields?: (keyof T)[]
  defaultSort?: keyof T & string
  defaultDir?: 'asc' | 'desc'
  perPage?: number
}

export function useResource<T extends Record<string, unknown>>(
  rows: Ref<T[]> | ComputedRef<T[]>,
  options: ResourceOptions<T> = {},
) {
  const { searchFields, defaultSort, defaultDir = 'desc', perPage: initialPerPage = 12 } = options

  const search = ref('')
  const filters = ref<Record<string, string>>({})
  const sort = ref<string | null>(defaultSort ?? null)
  const dir = ref<'asc' | 'desc'>(defaultDir)
  const page = ref(1)
  const perPage = ref(initialPerPage)
  const selected = ref<Set<string>>(new Set())

  // Any change to what's being looked at resets to the first page — otherwise
  // filtering on page 4 silently shows an empty table.
  watch([search, filters, perPage], () => { page.value = 1 }, { deep: true })

  const matches = (row: T, needle: string) => {
    const fields = searchFields ?? (Object.keys(row) as (keyof T)[])
    return fields.some((f) => {
      const v = row[f]
      return typeof v === 'string' || typeof v === 'number'
        ? String(v).toLowerCase().includes(needle)
        : false
    })
  }

  const filtered = computed(() => {
    let out = rows.value ?? []

    const needle = search.value.trim().toLowerCase()
    if (needle) out = out.filter((r) => matches(r, needle))

    for (const [key, value] of Object.entries(filters.value)) {
      if (!value || value === 'all') continue
      out = out.filter((r) => String(r[key]) === value)
    }

    if (sort.value) {
      const k = sort.value
      const m = dir.value === 'desc' ? -1 : 1
      out = [...out].sort((a, b) => {
        const av = a[k]
        const bv = b[k]
        if (av == null) return 1
        if (bv == null) return -1
        if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * m
        return String(av).localeCompare(String(bv)) * m
      })
    }

    return out
  })

  const total = computed(() => filtered.value.length)
  const pageCount = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))

  const paged = computed(() => {
    const start = (page.value - 1) * perPage.value
    return filtered.value.slice(start, start + perPage.value)
  })

  /** Clicking a column header: first click sorts, next flips direction. */
  function toggleSort(key: string) {
    if (sort.value === key) dir.value = dir.value === 'asc' ? 'desc' : 'asc'
    else { sort.value = key; dir.value = 'asc' }
  }

  function setFilter(key: string, value: string) {
    filters.value = { ...filters.value, [key]: value }
  }

  function clearFilters() {
    filters.value = {}
    search.value = ''
  }

  const activeFilterCount = computed(
    () => Object.values(filters.value).filter((v) => v && v !== 'all').length,
  )

  // --- selection, scoped to the visible page ---
  const rowKey = (r: T) => String(r.id ?? JSON.stringify(r))
  const isSelected = (r: T) => selected.value.has(rowKey(r))
  const allOnPageSelected = computed(
    () => paged.value.length > 0 && paged.value.every(isSelected),
  )

  function toggleRow(r: T) {
    const next = new Set(selected.value)
    const k = rowKey(r)
    next.has(k) ? next.delete(k) : next.add(k)
    selected.value = next
  }

  function toggleAllOnPage() {
    const next = new Set(selected.value)
    const all = allOnPageSelected.value
    for (const r of paged.value) all ? next.delete(rowKey(r)) : next.add(rowKey(r))
    selected.value = next
  }

  function clearSelection() {
    selected.value = new Set()
  }

  /** Shape suitable for handing straight to `useAdminList` once the API lands. */
  const query = computed<ListQuery>(() => ({
    page: page.value,
    perPage: perPage.value,
    search: search.value || undefined,
    sort: sort.value ?? undefined,
    dir: dir.value,
    ...filters.value,
  }))

  return {
    search, filters, sort, dir, page, perPage,
    filtered, paged, total, pageCount,
    toggleSort, setFilter, clearFilters, activeFilterCount,
    selected, isSelected, toggleRow, toggleAllOnPage, clearSelection,
    allOnPageSelected,
    selectedCount: computed(() => selected.value.size),
    query,
  }
}
