import type { GeoResult } from '~/utils/geo'

/**
 * The country the visitor is connecting from, resolved server-side.
 *
 * Two passes, because the strongest anti-VPN signal only exists in the
 * browser:
 *   1. During SSR the Nitro route answers from the edge header / IP alone, so
 *      the flag is already correct in the first paint for most visitors.
 *   2. Once mounted we re-ask with the browser's timezone. A VPN moves the
 *      exit IP but not the operating system clock, so this pass is what
 *      corrects the flag for someone tunnelling through another country.
 *
 * State is shared via `useState`, so extra callers cost nothing.
 */
export function useVisitorCountry() {
  const config = useRuntimeConfig()
  const geo = useState<GeoResult>('visitor-geo', () => ({
    country: config.public.geo.fallbackCountry,
    source: 'fallback',
    confidence: 'low',
    vpnSuspected: false,
  }))

  // `useRequestFetch` forwards the incoming headers, without which the SSR
  // call would geolocate the server rather than the visitor.
  const requestFetch = useRequestFetch()

  const { refresh } = useAsyncData('visitor-geo-fetch', async () => {
    const hints = import.meta.client
      ? { tz: Intl.DateTimeFormat().resolvedOptions().timeZone, offset: new Date().getTimezoneOffset() }
      : {}
    geo.value = await requestFetch<GeoResult>('/api/geo', { query: hints })
    return geo.value
  }, { server: true, immediate: true })

  // Second pass: same endpoint, now with the timezone hints attached.
  onMounted(() => refresh())

  return {
    geo: readonly(geo),
    country: computed(() => geo.value.country),
  }
}
