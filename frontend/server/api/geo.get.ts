import { resolveGeo } from '../utils/geo'

/**
 * Resolve the visitor's country. Called once during SSR (no hints, IP only)
 * and once from the browser with timezone hints, which is what lets the
 * resolver see through a VPN exit.
 *
 * The hints are advisory: they can only redirect the answer to the country
 * their IANA zone maps to, and only when the IP itself looks untrustworthy.
 * There is no request shape that lets a caller name an arbitrary country.
 */
export default defineEventHandler(async (event) => {
  const query = getQuery(event)
  const offset = Number(query.offset)

  const geo = await resolveGeo(event, {
    timezone: typeof query.tz === 'string' ? query.tz : null,
    offsetMinutes: Number.isFinite(offset) ? offset : null,
  })

  // Per-visitor answer — must never be shared by a CDN cache.
  setHeader(event, 'cache-control', 'private, no-store')
  return geo
})
