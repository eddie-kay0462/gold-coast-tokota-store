import type { Category } from '~/types'

/**
 * The catalogue's categories, from `GET /admin/categories`.
 *
 * That endpoint answers `{ categories, collections }` together (the product
 * form wants both), not a bare list, so reading it as a list gave an object.
 * The fixtures are a bare list; both are handled here, once.
 *
 * `followType` is how the product forms fill Type in from the category: it
 * takes the new category's usual type unless someone has typed their own,
 * meaning anything other than blank or the old category's usual type.
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
  const usualType = (id: string) => categories.value.find((c) => String(c.id) === id)?.productType ?? ''
  function followType(type: string, fromId: string, toId: string): string {
    const next = usualType(toId)
    const current = type.trim()
    return next && (!current || current === usualType(fromId)) ? next : type
  }
  return { categories, options, pending, refresh, followType }
}
