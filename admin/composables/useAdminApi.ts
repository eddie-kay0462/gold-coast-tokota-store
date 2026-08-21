import type { Envelope, ListQuery } from '~/types'
import { resolveFixture } from '~/fixtures'

/**
 * The one way this app talks to data.
 *
 * The Laravel admin API does not exist yet — `backend/routes/api.php` defines
 * exactly two public routes, and every `/admin/*` path returns 404. Rather than
 * build screens against nothing, `adminFetch` tries the real endpoint and falls
 * back to bundled fixtures when it isn't there. As each endpoint lands the
 * corresponding screen starts showing real data with no code change.
 *
 * Modes, via NUXT_PUBLIC_ADMIN_DATA:
 *   auto     — try the API, fall back to fixtures (default)
 *   live     — API only; a failure is an error the caller must handle
 *   fixtures — never call the API
 *
 * The fallback is never silent: `isDemoData` drives a persistent chip in the
 * header, and per-path status is inspectable on /settings. Invented numbers
 * must always look like invented numbers.
 */

/**
 * Laravel API Resources emit snake_case — see `PageResource` and
 * `SiteSettingResource`, the two that exist today. The TypeScript models are
 * camelCase, as Vue code should be. Rather than make every model ugly or every
 * call site remember, responses are normalised here, once.
 *
 * This was found by pointing the app at a stub API: fields arrived `undefined`
 * because `whatsapp_number` never became `whatsappNumber`. Worth catching
 * before the real endpoints land rather than after.
 *
 * Only object KEYS are converted, and only where they look like snake_case
 * field names. Values are untouched, so a `jsonb` payload like
 * `variant_attributes: { size: '42' }` keeps its data intact.
 */
function camelize(key: string): string {
  return key.replace(/_([a-z0-9])/g, (_, c: string) => c.toUpperCase())
}

function normalizeKeys(input: unknown): unknown {
  if (Array.isArray(input)) return input.map(normalizeKeys)
  if (input === null || typeof input !== 'object') return input
  if (input instanceof Date) return input

  const out: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(input as Record<string, unknown>)) {
    out[camelize(k)] = normalizeKeys(v)
  }
  return out
}

export type DataMode = 'auto' | 'live' | 'fixtures'
export type DataSource = 'live' | 'fixture'

interface PathStatus {
  path: string
  source: DataSource
  at: number
  note?: string
}

export interface AdminFetchOptions {
  query?: ListQuery
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
}

export interface AdminResult<T> {
  data: T
  meta: Record<string, unknown> | null
  source: DataSource
}

// Module-level so the registry survives across composable calls without a
// store; it's diagnostic state, not application state.
const statuses = new Map<string, PathStatus>()

export function useAdminApi() {
  const config = useRuntimeConfig()
  const mode = (config.public.adminData as DataMode) || 'auto'
  const base = config.public.apiBase as string

  // useState so every consumer shares one reactive flag across the app.
  const usingFixtures = useState<boolean>('admin-using-fixtures', () => mode === 'fixtures')
  const liveEndpoints = useState<number>('admin-live-endpoints', () => 0)

  function record(path: string, source: DataSource, note?: string) {
    statuses.set(path, { path, source, at: Date.now(), note })
    if (source === 'fixture') usingFixtures.value = true
    else liveEndpoints.value += 1
  }

  function fromFixture<T>(path: string, query: ListQuery, note?: string): AdminResult<T> {
    const hit = resolveFixture(path, query)
    if (!hit.found) {
      throw createError({
        statusCode: 404,
        statusMessage: `No fixture for ${path}. Add it to fixtures/index.ts, or point NUXT_PUBLIC_ADMIN_DATA at a live API.`,
      })
    }
    record(path, 'fixture', note)
    return { data: hit.data as T, meta: hit.meta ?? null, source: 'fixture' }
  }

  async function adminFetch<T>(
    path: string,
    options: AdminFetchOptions = {},
  ): Promise<AdminResult<T>> {
    const { query = {}, method = 'GET', body } = options

    if (mode === 'fixtures') return fromFixture<T>(path, query)

    try {
      const res = await $fetch<Envelope<T> | T>(`${base}${path}`, {
        method,
        query: method === 'GET' ? query : undefined,
        body: method === 'GET' ? undefined : (body as Record<string, unknown>),
        // Sanctum SPA cookie auth needs the session cookie on every call.
        credentials: 'include',
        headers: { Accept: 'application/json' },
        retry: 0,
      })

      record(path, 'live')

      // Unwrap the project's { data, meta, errors } envelope when present;
      // tolerate a bare payload, since not every controller sets `meta`.
      const enveloped = res as Envelope<T>
      const isEnvelope = res && typeof res === 'object' && 'data' in (res as object)
      const payload = isEnvelope ? enveloped.data : (res as T)
      return {
        data: normalizeKeys(payload) as T,
        meta: isEnvelope ? ((normalizeKeys(enveloped.meta ?? null) as Record<string, unknown> | null)) : null,
        source: 'live',
      }
    } catch (err: unknown) {
      if (mode === 'live') throw err

      const status = (err as { statusCode?: number; status?: number })?.statusCode
        ?? (err as { status?: number })?.status
      const note = status
        ? `API returned ${status}`
        : 'API unreachable'
      return fromFixture<T>(path, query, note)
    }
  }

  /**
   * Nuxt-aware list fetch. Wraps `useAsyncData` so pages get the usual
   * pending/error/refresh handles without repeating the boilerplate that the
   * old scaffold copied into every page.
   */
  function useAdminList<T>(key: string, path: string, query?: Ref<ListQuery> | ListQuery) {
    const q = isRef(query) ? query : ref(query ?? {})
    const state = useAsyncData<AdminResult<T[]>>(
      key,
      () => adminFetch<T[]>(path, { query: q.value }),
      { watch: [q], default: () => ({ data: [] as T[], meta: null, source: 'fixture' as const }) },
    )
    return {
      ...state,
      items: computed(() => state.data.value?.data ?? []),
      total: computed(() => Number(state.data.value?.meta?.total ?? state.data.value?.data?.length ?? 0)),
      source: computed<DataSource>(() => state.data.value?.source ?? 'fixture'),
    }
  }

  function useAdminItem<T>(key: string, path: string) {
    const state = useAsyncData<AdminResult<T>>(key, () => adminFetch<T>(path))
    return {
      ...state,
      item: computed(() => state.data.value?.data ?? null),
      source: computed<DataSource>(() => state.data.value?.source ?? 'fixture'),
    }
  }

  return {
    mode,
    adminFetch,
    useAdminList,
    useAdminItem,
    isDemoData: computed(() => usingFixtures.value),
    liveEndpointCount: computed(() => liveEndpoints.value),
    pathStatuses: () => Array.from(statuses.values()),
  }
}
