<script setup lang="ts">
import { LEGAL_SLUGS, POLICY_DRAFTS, type LegalSlug } from '~/utils/policyContent'

/**
 * The five footer legal pages, all through the shared prose template.
 *
 * `validate` rather than a bare param: a `[slug]` that rendered an empty
 * article for any string would be an open content hole, and would quietly
 * publish a URL for anything a crawler guessed. Unknown slugs 404, the same way
 * `pages/blog/[slug].vue` handles an unknown post.
 */
definePageMeta({
  validate: (route) => LEGAL_SLUGS.includes(route.params.slug as LegalSlug),
})

const route = useRoute()
const slug = computed(() => route.params.slug as LegalSlug)
const draft = computed(() => POLICY_DRAFTS[slug.value]!)

useSeoMeta({
  title: () => `${draft.value.title} — Gold Coast Tokota`,
  description: () => draft.value.summary,
  ogTitle: () => `${draft.value.title} — Gold Coast Tokota`,
  ogDescription: () => draft.value.summary,
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <!-- Keyed so navigating between two legal pages refetches rather than
         reusing the first page's resolved content. -->
    <ContentPolicyArticle :key="slug" :slug="slug" footer-heading="More policies">
      <template #footer>
        <ul class="flex w-full flex-col items-start gap-1">
          <li v-for="other in LEGAL_SLUGS.filter((s) => s !== slug)" :key="other" class="w-full">
            <NuxtLink
              :to="`/legal/${other}`"
              class="-my-3 flex min-h-[44px] items-center py-3 text-label text-muted underline hover:text-graphite"
            >{{ POLICY_DRAFTS[other]!.title }}</NuxtLink>
          </li>
        </ul>
      </template>
    </ContentPolicyArticle>
  </div>
</template>
