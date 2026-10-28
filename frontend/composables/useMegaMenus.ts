import { categoryNav, type MegaMenu, type NavItem } from '~/utils/navigation'

interface ApiMegaMenu extends MegaMenu {
  key: string
  label: string
  to: string
}

/**
 * Header row 3 with each mega menu as the API builds it
 * (`GET /navigation/menus`): links only to styles and departments that have
 * products in that menu, and the image tiles chosen in the admin (or the
 * menu's own product photos where none is chosen).
 *
 * Fetched once during SSR and shared by the desktop panel and the mobile
 * drawer. If the API can't be reached, the built-in menus in
 * `utils/navigation.ts` stand in, so the header never loses its menus.
 */
export function useMegaMenus() {
  const config = useRuntimeConfig()
  const { data } = useAsyncData(
    'mega-menus',
    () => $fetch<{ data: ApiMegaMenu[] }>(`${config.public.apiBase}/navigation/menus`).then((res) => res.data),
    { default: () => [] as ApiMegaMenu[] },
  )

  return computed<NavItem[]>(() => categoryNav.map((item) => {
    const live = data.value.find((menu) => menu.key === item.key)
    return live
      ? { ...item, label: live.label, to: live.to, menu: { columns: live.columns, promos: live.promos } }
      : item
  }))
}
