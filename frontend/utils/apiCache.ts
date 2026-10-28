/**
 * The API reads the storefront server may share between visitors for a minute:
 * public catalogue and content that is the same for everyone. Prices arrive in
 * GHS and are converted in the page with the display rate (also cached here;
 * checkout locks its own rate server-side), so a cached answer is right for
 * every currency. Anything per visitor or time-critical (stock polling, workshop
 * capacity, orders, accounts) is not on this list and always goes straight to
 * the API.
 */
export const API_CACHE_PREFIX = '/_api-cache'

const CACHEABLE = [
  /^\/products$/,
  /^\/products\/facets$/,
  /^\/products\/recommendations$/,
  /^\/products\/[\w-]+$/,
  /^\/blog-posts$/,
  /^\/blog-posts\/[\w-]+$/,
  /^\/pages\/[\w-]+$/,
  /^\/navigation\/menus$/,
  /^\/site-settings$/,
  /^\/fx-rate$/,
  /^\/gift-cards\/settings$/,
]

/** Whether `path` (relative to `apiBase`, query string allowed) may be cached. */
export function isCacheableApiPath(path: string): boolean {
  const bare = path.split('?')[0]!
  return CACHEABLE.some((pattern) => pattern.test(bare))
}
