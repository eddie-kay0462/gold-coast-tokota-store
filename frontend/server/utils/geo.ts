import { createHmac, timingSafeEqual } from 'node:crypto'
import type { H3Event } from 'h3'
import type { GeoResult } from '~/utils/geo'
import { countryForTimezone } from './timezone-countries'

export interface GeoHints {
  /** IANA zone id from the browser (`Intl.DateTimeFormat().resolvedOptions().timeZone`). */
  timezone?: string | null
  /** Minutes returned by `Date.prototype.getTimezoneOffset()`. */
  offsetMinutes?: number | null
}

const COOKIE_NAME = 'gct_geo'
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7
const LOOKUP_TIMEOUT_MS = 1500
const LOOKUP_CACHE_TTL_MS = 60 * 60 * 1000

/** Headers set by a CDN/edge in front of us. These are the only client-supplied
 *  country values we trust, and only because the edge overwrites whatever the
 *  visitor sent. Anything the browser can set on its own is ignored. */
const EDGE_COUNTRY_HEADERS = [
  'cf-ipcountry', // Cloudflare
  'x-vercel-ip-country', // Vercel
  'x-nf-geo-country', // Netlify (also ships a JSON blob, this is the plain code)
  'fastly-geo-countrycode',
  'x-appengine-country',
  'x-client-geo-country',
]

/** Substrings that mark an org/ISP as a hosting provider or an outright VPN
 *  brand. Consumer broadband and mobile carriers do not match these, so a hit
 *  is a strong signal that the IP is an exit node rather than a home line. */
const HOSTING_ORG_PATTERNS = [
  'vpn', 'proxy', 'tor exit', 'relay', 'anonymous', 'privacy',
  'hosting', 'host europe', 'datacenter', 'data center', 'dedicated', 'server',
  'cloud', 'colocation', 'colo ', 'virtual', 'vps',
  'nordvpn', 'expressvpn', 'surfshark', 'cyberghost', 'private internet access',
  'mullvad', 'protonvpn', 'ipvanish', 'hidemyass', 'purevpn', 'windscribe',
  'm247', 'datacamp', 'datapacket', 'packethub', 'zenlayer', 'leaseweb',
  'digitalocean', 'linode', 'akamai connected cloud', 'vultr', 'choopa',
  'hetzner', 'ovh', 'scaleway', 'contabo', 'oracle', 'amazon', 'aws',
  'google llc', 'google cloud', 'microsoft', 'azure', 'alibaba', 'tencent',
  'cogent', 'hurricane electric', 'quadranet', 'psychz', 'gcore', 'g-core',
]

interface IpLookup {
  country: string | null
  timezone: string | null
  org: string | null
  /** Provider-declared proxy/VPN flag, when the provider offers one. */
  flaggedByProvider: boolean
}

const lookupCache = new Map<string, { at: number, value: IpLookup }>()

function normaliseCountry(value: unknown): string | null {
  if (typeof value !== 'string') return null
  const code = value.trim().toUpperCase()
  // Cloudflare uses XX for unknown and T1 for Tor.
  if (!/^[A-Z]{2}$/.test(code) || code === 'XX' || code === 'T1') return null
  return code
}

/** RFC1918 / loopback / link-local / CGNAT — nothing to look up. */
function isPrivateIp(ip: string): boolean {
  if (ip === '::1' || ip === '127.0.0.1' || ip.startsWith('fe80:') || ip.startsWith('fc') || ip.startsWith('fd')) return true
  const parts = ip.split('.').map(Number)
  if (parts.length !== 4 || parts.some(Number.isNaN)) return false
  const [a, b] = parts as [number, number, number, number]
  return a === 10
    || a === 127
    || (a === 172 && b >= 16 && b <= 31)
    || (a === 192 && b === 168)
    || (a === 169 && b === 254)
    || (a === 100 && b >= 64 && b <= 127)
}

function edgeCountry(event: H3Event): string | null {
  for (const header of EDGE_COUNTRY_HEADERS) {
    const code = normaliseCountry(getRequestHeader(event, header))
    if (code) return code
  }
  return null
}

function clientIp(event: H3Event): string | null {
  // `xForwardedFor` is only safe because the deploy target terminates TLS at a
  // proxy we control; a direct-to-node deployment must not enable it.
  const config = useRuntimeConfig(event)
  const ip = getRequestIP(event, { xForwardedFor: config.geo.trustProxyHeaders })
  return ip ? ip.replace(/^::ffff:/, '').split('%')[0]! : null
}

async function lookupIp(ip: string, endpoint: string): Promise<IpLookup | null> {
  const cached = lookupCache.get(ip)
  if (cached && Date.now() - cached.at < LOOKUP_CACHE_TTL_MS) return cached.value

  try {
    const data = await $fetch<Record<string, any>>(endpoint.replace('{ip}', encodeURIComponent(ip)), {
      signal: AbortSignal.timeout(LOOKUP_TIMEOUT_MS),
      retry: 0,
    })
    // Field names differ per provider; accept the shapes of the common free
    // ones (ipwho.is, ip-api.com, ipapi.co) without pinning us to one.
    const connection = (data.connection ?? {}) as Record<string, any>
    const value: IpLookup = {
      country: normaliseCountry(data.country_code ?? data.countryCode ?? data.country),
      timezone: (data.timezone?.id ?? data.timezone ?? data.time_zone?.id ?? null) as string | null,
      org: (connection.org ?? connection.isp ?? data.org ?? data.isp ?? data.asn_org ?? null) as string | null,
      flaggedByProvider: Boolean(data.proxy || data.hosting || data.vpn || data.security?.vpn || data.security?.proxy),
    }
    lookupCache.set(ip, { at: Date.now(), value })
    return value
  }
  catch {
    return null
  }
}

function looksLikeHosting(lookup: IpLookup | null): boolean {
  if (!lookup) return false
  if (lookup.flaggedByProvider) return true
  const org = lookup.org?.toLowerCase()
  if (!org) return false
  return HOSTING_ORG_PATTERNS.some((pattern) => org.includes(pattern))
}

/**
 * Cross-check the claimed IANA zone against the browser's own UTC offset.
 *
 * `Intl` and `Date.getTimezoneOffset()` come from the same clock, so an honest
 * browser always agrees with itself. A visitor hand-editing the timezone hint
 * to move the flag has to keep both consistent, which a spoofed zone string on
 * its own does not.
 */
function timezoneMatchesOffset(timezone: string, offsetMinutes: number | null | undefined): boolean {
  if (offsetMinutes === null || offsetMinutes === undefined || !Number.isFinite(offsetMinutes)) return true
  try {
    const name = new Intl.DateTimeFormat('en-US', { timeZone: timezone, timeZoneName: 'longOffset' })
      .formatToParts(new Date())
      .find((part) => part.type === 'timeZoneName')?.value
    if (!name) return true
    // "GMT+5:30" / "GMT-8" / plain "GMT".
    const match = /^GMT(?:([+-])(\d{1,2})(?::(\d{2}))?)?$/.exec(name)
    if (!match) return true
    const sign = match[1] === '-' ? -1 : 1
    const zoneOffset = sign * (Number(match[2] ?? 0) * 60 + Number(match[3] ?? 0))
    // getTimezoneOffset() is inverted relative to the UTC offset.
    return zoneOffset === -offsetMinutes
  }
  catch {
    return false // Unparseable zone id — treat the hint as untrustworthy.
  }
}

/** Country for a browser timezone hint, or null if the hint is inconsistent. */
function countryForHints(hints: GeoHints): string | null {
  const timezone = hints.timezone
  if (!timezone || !timezoneMatchesOffset(timezone, hints.offsetMinutes)) return null
  return countryForTimezone(timezone)
}

// --- signed cache cookie ------------------------------------------------
// The *facts* about the connection (not the final verdict) round-trip through
// a cookie so we hit the lookup provider once a week per visitor instead of
// once a request. Caching facts rather than the verdict matters: the first
// (SSR) request has no browser timezone hint and the follow-up one does, so
// the verdict has to stay re-derivable. The cookie is signed because an
// unsigned one would be a free country override — exactly the bypass we are
// trying to close.

interface GeoFacts {
  /** Country from the edge header or IP lookup, if either produced one. */
  ipCountry: string | null
  fromEdge: boolean
  hosting: boolean
  ipTimezone: string | null
}

function sign(payload: string, secret: string): string {
  return createHmac('sha256', secret).update(payload).digest('base64url')
}

function readCookie(event: H3Event, secret: string): GeoFacts | null {
  const raw = getCookie(event, COOKIE_NAME)
  if (!raw) return null
  const separator = raw.lastIndexOf('.')
  if (separator < 1) return null
  const payload = raw.slice(0, separator)
  const signature = Buffer.from(raw.slice(separator + 1))
  const expected = Buffer.from(sign(payload, secret))
  if (signature.length !== expected.length || !timingSafeEqual(signature, expected)) return null
  try {
    const parsed = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8')) as GeoFacts
    if (typeof parsed !== 'object' || parsed === null) return null
    return {
      ipCountry: normaliseCountry(parsed.ipCountry),
      fromEdge: Boolean(parsed.fromEdge),
      hosting: Boolean(parsed.hosting),
      ipTimezone: typeof parsed.ipTimezone === 'string' ? parsed.ipTimezone : null,
    }
  }
  catch {
    return null
  }
}

function writeCookie(event: H3Event, facts: GeoFacts, secret: string) {
  const payload = Buffer.from(JSON.stringify(facts), 'utf8').toString('base64url')
  setCookie(event, COOKIE_NAME, `${payload}.${sign(payload, secret)}`, {
    httpOnly: true,
    sameSite: 'lax',
    secure: !import.meta.dev,
    path: '/',
    maxAge: COOKIE_MAX_AGE,
  })
}

/**
 * Work out which country the visitor is connecting from.
 *
 * Order of trust:
 *   1. Edge geo header (CDN-set, not forgeable by the browser).
 *   2. IP geolocation of the connecting address.
 *   3. Browser timezone — used only to *correct* 1/2 when the IP looks like a
 *      hosting/VPN exit, never as a free-standing override, and only when it
 *      resolves to a real country through the IANA table.
 *
 * A commercial VPN changes the exit IP but leaves the operating system clock
 * alone, so an IP on a datacenter ASN whose country disagrees with the
 * browser's timezone is read as "VPN, and the timezone is the honest signal".
 */
export async function resolveGeo(event: H3Event, hints: GeoHints = {}): Promise<GeoResult> {
  const config = useRuntimeConfig(event)
  const secret = config.geo.secret
  const fallback = normaliseCountry(config.public.geo.fallbackCountry) ?? 'GH'
  const tzCountry = countryForHints(hints)

  let facts = readCookie(event, secret)

  if (!facts) {
    const fromEdge = edgeCountry(event)
    const ip = clientIp(event)
    const lookup = !fromEdge && ip && !isPrivateIp(ip)
      ? await lookupIp(ip, config.geo.lookupEndpoint)
      : null

    facts = {
      ipCountry: fromEdge ?? lookup?.country ?? null,
      fromEdge: Boolean(fromEdge),
      hosting: looksLikeHosting(lookup),
      ipTimezone: lookup?.timezone ?? null,
    }
    writeCookie(event, facts, secret)
  }

  // Provider timezone vs browser timezone is a second mismatch signal: it
  // catches residential-proxy exits that no ASN list would flag.
  const timezoneMismatch = Boolean(
    tzCountry && facts.ipTimezone && countryForTimezone(facts.ipTimezone) !== tzCountry,
  )
  const countryMismatch = Boolean(tzCountry && facts.ipCountry && tzCountry !== facts.ipCountry)
  const vpnSuspected = facts.hosting || countryMismatch || timezoneMismatch

  if (tzCountry && countryMismatch && (facts.hosting || timezoneMismatch)) {
    return { country: tzCountry, source: 'timezone', confidence: 'medium', vpnSuspected: true }
  }
  if (facts.ipCountry) {
    return {
      country: facts.ipCountry,
      source: facts.fromEdge ? 'edge' : 'ip',
      confidence: vpnSuspected ? 'low' : 'high',
      vpnSuspected,
    }
  }
  if (tzCountry) {
    // No usable IP signal at all (local dev, private network, provider down).
    return { country: tzCountry, source: 'timezone', confidence: 'low', vpnSuspected: false }
  }
  return { country: fallback, source: 'fallback', confidence: 'low', vpnSuspected: false }
}
