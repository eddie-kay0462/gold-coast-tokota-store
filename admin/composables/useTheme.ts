/**
 * Dark / light / system theme.
 *
 * Three states, not two. "system" is the default and genuinely follows the OS
 * live — a user who flips their Mac to dark at sunset sees the dashboard
 * follow without touching anything.
 *
 * Persistence is a cookie rather than localStorage. The app is SPA-only today,
 * so either would work, but the cookie is readable by the inline boot script in
 * `nuxt.config.ts` that stamps the class before first paint, and it matches the
 * storefront's SSR-safe convention (see README State Management, which rules
 * out localStorage for anything the first render depends on).
 */
export type ThemePreference = 'light' | 'dark' | 'system'

export const THEME_COOKIE = 'gct-admin-theme'

const isThemePreference = (v: unknown): v is ThemePreference =>
  v === 'light' || v === 'dark' || v === 'system'

export function useTheme() {
  const cookie = useCookie<ThemePreference>(THEME_COOKIE, {
    default: () => 'system',
    maxAge: 60 * 60 * 24 * 365,
    sameSite: 'lax',
    path: '/',
  })

  const preference = useState<ThemePreference>('theme-preference', () =>
    isThemePreference(cookie.value) ? cookie.value : 'system',
  )

  // What the OS is currently asking for. Only meaningful when preference is
  // 'system', but tracked always so switching back to 'system' is instant.
  const systemPrefersDark = useState<boolean>('theme-system-dark', () => false)

  const resolved = computed<'light' | 'dark'>(() =>
    preference.value === 'system'
      ? systemPrefersDark.value
        ? 'dark'
        : 'light'
      : preference.value,
  )

  const isDark = computed(() => resolved.value === 'dark')

  function apply() {
    if (typeof document === 'undefined') return
    document.documentElement.classList.toggle('dark', isDark.value)
    document.documentElement.dataset.theme = resolved.value
  }

  function setPreference(next: ThemePreference) {
    preference.value = next
    cookie.value = next
    apply()
  }

  /** Cycles light → dark → system, matching the sun icon in the Figma header. */
  function cycle() {
    const order: ThemePreference[] = ['light', 'dark', 'system']
    const i = order.indexOf(preference.value)
    setPreference(order[(i + 1) % order.length]!)
  }

  // Bind to the OS. Called once from the theme plugin; safe to call again.
  function watchSystem() {
    if (typeof window === 'undefined' || !window.matchMedia) return
    const mq = window.matchMedia('(prefers-color-scheme: dark)')
    systemPrefersDark.value = mq.matches
    const onChange = (e: MediaQueryListEvent) => {
      systemPrefersDark.value = e.matches
      apply()
    }
    mq.addEventListener('change', onChange)
    apply()
    return () => mq.removeEventListener('change', onChange)
  }

  return {
    preference: readonly(preference),
    resolved,
    isDark,
    setPreference,
    cycle,
    watchSystem,
    apply,
  }
}
