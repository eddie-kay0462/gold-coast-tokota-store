/**
 * What to tell someone when a write failed: the API's first validation
 * message, else its `message`, else the fallback. `null` for a 401/419,
 * because `useAdminApi` is already sending them to /login and a toast on top
 * of that is noise.
 */
export function failureMessage(err: unknown, fallback = 'Not saved. Try again in a moment.'): string | null {
  const e = err as { statusCode?: number; data?: { errors?: Record<string, string[]>; message?: string } }
  if (e.statusCode === 401 || e.statusCode === 419) return null
  if (!e.statusCode) return 'Couldn’t reach the server, so nothing was saved.'
  return Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? fallback
}
