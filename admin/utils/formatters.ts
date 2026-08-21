/**
 * Display formatters. Money lives in utils/currency.ts, which owns the
 * minor-units rule; this file covers everything else.
 */

const DAY = 864e5

export function formatDate(iso: string | null, opts?: Intl.DateTimeFormatOptions): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-GB', opts ?? {
    day: 'numeric', month: 'short', year: 'numeric',
  })
}

export function formatDateTime(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-GB', {
    day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
  })
}

export function formatTime(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

/**
 * Relative time, resolved against a supplied "now" so fixture timestamps read
 * correctly against the fixture clock rather than the wall clock.
 */
export function formatRelative(iso: string | null, now: Date = new Date()): string {
  if (!iso) return '—'
  const diff = now.getTime() - new Date(iso).getTime()
  const abs = Math.abs(diff)
  const future = diff < 0
  const rel = (n: number, unit: string) =>
    future ? `in ${n} ${unit}${n === 1 ? '' : 's'}` : `${n} ${unit}${n === 1 ? '' : 's'} ago`

  if (abs < 6e4) return future ? 'shortly' : 'just now'
  if (abs < 36e5) return rel(Math.round(abs / 6e4), 'minute')
  if (abs < DAY) return rel(Math.round(abs / 36e5), 'hour')
  if (abs < 7 * DAY) return rel(Math.round(abs / DAY), 'day')
  if (abs < 60 * DAY) return rel(Math.round(abs / (7 * DAY)), 'week')
  return rel(Math.round(abs / (30 * DAY)), 'month')
}

/** Days remaining, for the intern access countdown. Negative when lapsed. */
export function daysUntil(iso: string | null, now: Date = new Date()): number | null {
  if (!iso) return null
  return Math.ceil((new Date(iso).getTime() - now.getTime()) / DAY)
}

export function formatNumber(n: number): string {
  return new Intl.NumberFormat('en-GB').format(n)
}

export function formatPercent(n: number, digits = 1): string {
  return `${n > 0 ? '+' : ''}${n.toFixed(digits)}%`
}

export function formatBytes(bytes: number): string {
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  let v = bytes
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i++ }
  return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`
}

/** "pending_payment" / "inventory_conflict" → "Pending payment". */
export function humanise(key: string): string {
  const s = key.replace(/[_-]+/g, ' ').trim()
  return s.charAt(0).toUpperCase() + s.slice(1)
}

export function initials(name: string): string {
  return name.split(/\s+/).map((w) => w[0]).slice(0, 2).join('').toUpperCase()
}

export function truncate(s: string, max: number): string {
  return s.length <= max ? s : `${s.slice(0, max - 1)}…`
}
