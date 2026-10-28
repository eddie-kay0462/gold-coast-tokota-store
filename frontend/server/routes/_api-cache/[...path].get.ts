import { API_CACHE_PREFIX, isCacheableApiPath } from '~/utils/apiCache'

/**
 * A one-minute shared cache in front of the API's public catalogue reads,
 * used by `useApiFetch` while rendering pages. Pages themselves are not cached:
 * they carry each visitor's currency and cart from cookies, which a page cache
 * would serve to everyone.
 *
 * On a miss the API is asked with the storefront key and the visitor's address
 * (see the backend's `api` limiter). The address comes from X-Forwarded-For,
 * which Vercel sets itself, so a caller can't choose someone else's bucket.
 * Only paths in `utils/apiCache.ts` are served, so this is never an open proxy
 * to the rest of the API. Errors are not cached, so a brief outage clears as
 * soon as the API answers again.
 */
export default defineCachedEventHandler(
  async (event) => {
    const path = event.path.slice(API_CACHE_PREFIX.length)
    if (!isCacheableApiPath(path)) {
      throw createError({ statusCode: 404, statusMessage: 'Not found' })
    }

    const config = useRuntimeConfig(event)
    const ip = getRequestIP(event, { xForwardedFor: true })
    const key = config.storefrontApiKey as string

    try {
      return await $fetch(path, {
        baseURL: config.public.apiBase as string,
        headers: {
          Accept: 'application/json',
          ...(key && ip ? { 'X-Storefront-Key': key, 'X-Storefront-Client-IP': ip } : {}),
        },
      })
    } catch (error) {
      const status = (error as { statusCode?: number }).statusCode
      throw createError({ statusCode: status ?? 502, statusMessage: 'API request failed' })
    }
  },
  {
    name: 'api-cache',
    maxAge: 60,
    swr: true,
    getKey: (event) => event.path,
  },
)
