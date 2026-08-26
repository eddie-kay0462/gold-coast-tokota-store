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
 * Resolves one CMS page, and decides — in exactly one place — whether what came
 * back is approved copy or a draft that must be labelled as such.
 *
 * Ten routes go through here. If each one re-implemented "fetch, catch, pick a
 * fallback, decide whether to warn", the day someone copy-pastes it slightly
 * wrong is the day the site publishes unreviewed legal text with no banner. So
 * the rule lives here and the pages just render what they are told.
 *
 * A page counts as draft when any of these hold:
 *   - the endpoint failed or the slug is not in the CMS yet (fallback copy);
 *   - the CMS row is explicitly flagged `is_draft`;
 *   - the row exists but its body is empty, which is what a seeded-but-unwritten
 *     page looks like.
 */
export async function usePageContent(slug: string) {
  const config = useRuntimeConfig()

  const { data } = await useAsyncData(`page-${slug}`, () =>
    $fetch<{ data: ApiPage }>(`${config.public.apiBase}/pages/${slug}`)
      .catch(() => null),
  )

  const apiPage = computed(() => data.value?.data ?? null)

  const isDraft = computed(() => {
    const page = apiPage.value
    if (!page) return true
    if (page.is_draft) return true
    return !page.body?.trim()
  })

  const draft = computed<PolicyDraft | null>(() => POLICY_DRAFTS[slug] ?? null)

  const title = computed(() => {
    // Prefer the CMS title even on a draft row — an owner who has renamed the
    // page in admin means it, whether or not the body is finished.
    const fromApi = apiPage.value?.title?.trim()
    return fromApi || draft.value?.title || ''
  })

  const summary = computed(() => draft.value?.summary ?? null)

  /** Approved HTML body, or null when the draft sections should render. */
  const html = computed(() => (isDraft.value ? null : apiPage.value?.body ?? null))

  const sections = computed(() => (isDraft.value ? draft.value?.sections ?? [] : []))

  const updated = computed(
    () => apiPage.value?.updated_at ?? draft.value?.updated ?? null,
  )

  return { title, summary, html, sections, updated, isDraft }
}
