/**
 * Deterministic pseudo-random helpers.
 *
 * Fixtures must be stable: a table that reshuffles on every reload makes
 * screenshots useless and turns any visual diff into noise. Everything derives
 * from a fixed seed, so the demo data is identical on every machine and run.
 */
let state = 0x2f6f4f

export function reseed(n = 0x2f6f4f) {
  state = n
}

/** xorshift32 — small, fast, and repeatable. */
export function rand(): number {
  state ^= state << 13
  state ^= state >>> 17
  state ^= state << 5
  return ((state >>> 0) % 100000) / 100000
}

export const pick = <T,>(xs: readonly T[]): T => xs[Math.floor(rand() * xs.length)]!
export const int = (min: number, max: number) => min + Math.floor(rand() * (max - min + 1))
export const chance = (p: number) => rand() < p

/**
 * All fixture dates are relative to this fixed "now" rather than the real
 * clock, so "2 hours ago" stays 2 hours ago and the demo never drifts into
 * showing a dashboard full of month-old orders.
 */
export const NOW = new Date('2026-08-21T14:30:00Z')

export const iso = (d: Date) => d.toISOString()
export const daysAgo = (n: number, h = 0) =>
  iso(new Date(NOW.getTime() - n * 864e5 - h * 36e5))
export const daysAhead = (n: number, h = 0) =>
  iso(new Date(NOW.getTime() + n * 864e5 + h * 36e5))
export const hoursAgo = (n: number) => iso(new Date(NOW.getTime() - n * 36e5))
export const minsAgo = (n: number) => iso(new Date(NOW.getTime() - n * 6e4))
export const ymd = (isoStr: string) => isoStr.slice(0, 10)

/** Avatars are generated, not fetched — the CSP-free, offline-safe option. */
export function avatarFor(name: string): string {
  const initials = name.split(/\s+/).map((w) => w[0]).slice(0, 2).join('').toUpperCase()
  const hues = ['#D4AF37', '#7A5A3A', '#2F6F4F', '#262626', '#8C6E4A', '#4C6B5A']
  let h = 0
  for (const c of name) h = (h * 31 + c.charCodeAt(0)) >>> 0
  const bg = hues[h % hues.length]
  const svg =
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">` +
    `<rect width="64" height="64" rx="32" fill="${bg}"/>` +
    `<text x="32" y="41" font-family="Helvetica,Arial,sans-serif" font-size="24"` +
    ` fill="#fff" text-anchor="middle">${initials}</text></svg>`
  return `data:image/svg+xml,${encodeURIComponent(svg)}`
}

export const ghs = (amount: number) => ({ amount, currency: 'GHS' as const })
export const usd = (amount: number) => ({ amount, currency: 'USD' as const })
