import type { PolicyDraft } from '~/utils/policyContent'
import { POLICY_DRAFTS } from '~/utils/policyContent'

/** Shape of `PageResource` from the Laravel API (Feature 9). */
export type ApiPage = {
  id: number
  slug: string
  title: string
  body: string | null
  is_draft?: boolean
  updated_at: string
}

/**
 * Resolves one CMS page, and decides (in exactly one place) whether what came
 * back is approved copy or a draft that must be labelled as such.
 *
 * Ten routes go through here. If each one re-implemented "fetch, catch, pick a
 * fallback, decide whether to warn", the day someone copy-pastes it slightly
 * wrong is the day the site publishes unreviewed legal text with no banner. So
 * the rule lives here and the pages just render what they are told.
 *
 * Approved CMS copy (a row that is not `is_draft` and has a body) always wins.
 * Without it the built-in copy in `utils/policyContent.ts` renders, and the
 * page counts as a draft unless that copy is marked `approved`: the Shipping,
 * Returns, Privacy and Terms pages, which are the client's own text from
 * GOLD_COAST_TOKOTA.md §8 to §11. Everything else built in is placeholder and
 * keeps its banner.
 */
export async function usePageContent(slug: string) {
  const apiFetch = useApiFetch()

  const { data } = await useAsyncData(`page-${slug}`, () =>
    apiFetch<{ data: ApiPage }>(`/pages/${slug}`)
      .catch(() => null),
  )

  const apiPage = computed(() => data.value?.data ?? null)

  const draft = computed<PolicyDraft | null>(() => POLICY_DRAFTS[slug] ?? null)

  const cmsApproved = computed(() => {
    const page = apiPage.value
    return !!page && !page.is_draft && !!page.body?.trim()
  })

  const isDraft = computed(() => !cmsApproved.value && !draft.value?.approved)

  const title = computed(() => {
    // Prefer the CMS title even on a draft row; an owner who has renamed the
    // page in admin means it, whether or not the body is finished.
    const fromApi = apiPage.value?.title?.trim()
    return fromApi || draft.value?.title || ''
  })

  const summary = computed(() => draft.value?.summary ?? null)

  /** Approved CMS HTML, or null when the built-in sections should render. */
  const html = computed(() => (cmsApproved.value ? apiPage.value!.body : null))

  const sections = computed(() => (cmsApproved.value ? [] : draft.value?.sections ?? []))

  const updated = computed(() =>
    cmsApproved.value
      ? apiPage.value!.updated_at
      : draft.value?.updated ?? apiPage.value?.updated_at ?? null,
  )

  return { title, summary, html, sections, updated, isDraft }
}
