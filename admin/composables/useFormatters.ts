import { NOW } from '~/fixtures'
import * as f from '~/utils/formatters'

/**
 * Formatters bound to the fixture clock.
 *
 * Fixture timestamps are generated relative to a fixed "now" so the demo does
 * not drift. Rendering them against the wall clock would make every "2 hours
 * ago" wrong. When live data replaces fixtures, `now` becomes `new Date()` in
 * one place instead of at every call site.
 */
export function useFormatters() {
  const { isDemoData } = useAdminApi()
  const now = computed(() => (isDemoData.value ? NOW : new Date()))

  return {
    ...f,
    formatRelative: (iso: string | null) => f.formatRelative(iso, now.value),
    daysUntil: (iso: string | null) => f.daysUntil(iso, now.value),
    now,
  }
}
