/**
 * The storefront's client for the Laravel API. Call it in `setup`, then use
 * the returned function in handlers.
 *
 * Writes need Sanctum's CSRF handshake. The storefront's origin is a Sanctum
 * *stateful* domain (`SANCTUM_STATEFUL_DOMAINS`), so any request a browser
 * sends from it runs through session + CSRF middleware — and without the
 * `X-XSRF-TOKEN` header every POST is a 419. Plain `$fetch` calls did exactly
 * that: newsletter, feedback, both booking forms and checkout all failed from
 * a real browser while passing every API test (test requests carry no Origin).
 *
 * So: before the first write, `GET /sanctum/csrf-cookie`; on every write, send
 * the token from the `XSRF-TOKEN` cookie, re-read each time because Laravel
 * rotates it at login/logout. `credentials: 'include'` also carries the
 * customer's session, which is how checkout attaches a signed-in customer.
 * The cookie is readable here because `SESSION_DOMAIN` is the shared parent
 * domain (`.goldcoasttokota.store`; `localhost` in development).
 */
type ApiOptions = Parameters<typeof $fetch>[1]

let csrfReady: Promise<unknown> | null = null

function xsrfToken(): string | null {
  if (import.meta.server) return null
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]!) : null
}

export function useApi() {
  const base = useRuntimeConfig().public.apiBase as string
  const origin = base.replace(/\/api\/v\d+\/?$/, '')

  return async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
    const method = String(options.method ?? 'GET').toUpperCase()
    const writing = method !== 'GET' && method !== 'HEAD'

    if (writing && !xsrfToken()) {
      csrfReady ??= $fetch(`${origin}/sanctum/csrf-cookie`, { credentials: 'include' })
        .finally(() => { csrfReady = null })
      await csrfReady
    }

    const token = writing ? xsrfToken() : null
    return $fetch<T>(`${base}${path}`, {
      ...options,
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        ...(options.headers as Record<string, string> | undefined),
      },
    }) as Promise<T>
  }
}
