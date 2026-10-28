/**
 * Telling "not there" apart from "couldn't ask".
 *
 * Pages used to treat any failed API call as a missing product or story, so a
 * rate-limited or briefly unreachable API showed real products as 404s and the
 * shop swapped in demo stock. Only the API saying 404 means not found; anything
 * else (429, 5xx, a network error) is a temporary failure and gets a "try
 * again" page.
 */
export function isNotFound(error: unknown): boolean {
  return (error as { statusCode?: number } | null)?.statusCode === 404
}

/** The error page for a temporary failure. Throw it; Nitro never caches it. */
export function apiUnavailable() {
  return createError({
    statusCode: 503,
    statusMessage: 'We could not load this page',
    fatal: true,
  })
}
