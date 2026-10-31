import type { Category } from '~/types'

/**
 * The catalogue's categories, from `GET /admin/categories`.
 *
 * That endpoint answers `{ categories, collections }` together (the product
 * form wants both), not a bare list, so reading it as a list gave an object.
 * The fixtures are a bare list; both are handled here, once.
 */
export function useCategories() {
  const { useAdminItem } = useAdminApi()
  const { item, pending, refresh } = useAdminItem<{ categories: Category[] } | Category[]>(
    'admin-categories', '/admin/categories',
  )
  const categories = computed<Category[]>(() => {
    const value = item.value
    return Array.isArray(value) ? value : value?.categories ?? []
  })
  const options = computed(() => [
    { value: '', label: 'No category' },
    ...categories.value.map((c) => ({ value: String(c.id), label: c.name })),
  ])
  return { categories, options, pending, refresh }
}
