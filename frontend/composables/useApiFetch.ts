import { getRequestIP } from 'h3'
import { API_CACHE_PREFIX, isCacheableApiPath } from '~/utils/apiCache'

/**
 * `$fetch` against the Laravel API for reads. Call it in `setup` (or a plugin),
 * then use the returned function anywhere, including inside `useAsyncData`.
 * Paths are relative to `apiBase`: `apiFetch('/products')`.
 *
 * While rendering on the server, every request leaves from the storefront's
 * Vercel address, so without help the whole site shares one visitor's rate
 * limit. On the server this attaches the shared `X-Storefront-Key` and the
 * visitor's own address, and the API limits per visitor instead (see the `api`
 * limiter in the backend's AppServiceProvider). Public catalogue reads go
 * through the storefront's one-minute cache (`server/routes/_api-cache`)
 * rather than to the API every time.
 *
 * In the browser it adds nothing: the request already comes from the visitor,
 * and the key must never ship to the client.
 */
export function useApiFetch() {
  const config = useRuntimeConfig()
  const base = config.public.apiBase as string

  if (!import.meta.server) {
    return $fetch.create({ baseURL: base, headers: { Accept: 'application/json' } })
  }

  const event = useRequestEvent()
  // Vercel sets X-Forwarded-For to the connecting address itself.
  const ip = event ? getRequestIP(event, { xForwardedFor: true }) : undefined
  const key = config.storefrontApiKey as string

  const direct = $fetch.create({
    baseURL: base,
    headers: {
      Accept: 'application/json',
      ...(key && ip ? { 'X-Storefront-Key': key, 'X-Storefront-Client-IP': ip } : {}),
    },
  })

  return (<T>(path: string, options: Parameters<typeof direct>[1] = {}) => {
    const method = String(options.method ?? 'GET').toUpperCase()
    if (method !== 'GET' || !isCacheableApiPath(path)) return direct<T>(path, options)

    // Handled inside this server process, not over the network.
    return $fetch<T>(`${API_CACHE_PREFIX}${path}`, {
      ...options,
      headers: ip ? { 'X-Forwarded-For': ip } : {},
    })
  }) as unknown as typeof direct
}
