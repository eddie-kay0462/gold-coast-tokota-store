<script setup lang="ts">
import { HELP_SLUGS, HELP_TOPICS, POLICY_DRAFTS, type HelpSlug } from '~/utils/policyContent'

/** Help articles. `/returns` and `/shipping` 301 here — see nuxt.config.ts. */
definePageMeta({
  validate: (route) => HELP_SLUGS.includes(route.params.slug as HelpSlug),
})

const route = useRoute()
const slug = computed(() => route.params.slug as HelpSlug)
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
    <div class="page-gutter mx-auto w-full max-w-[1044px] pt-8">
      <NuxtLink
        to="/help"
        class="-my-3 inline-flex min-h-[44px] items-center py-3 text-caption text-muted underline hover:text-graphite"
      >← All help topics</NuxtLink>
    </div>

    <ContentPolicyArticle :key="slug" :slug="slug" footer-heading="Other help topics">
      <template #footer>
        <ul class="flex w-full flex-col items-start gap-1">
          <li v-for="topic in HELP_TOPICS.filter((t) => t.slug !== slug)" :key="topic.slug" class="w-full">
            <NuxtLink
              :to="`/help/${topic.slug}`"
              class="-my-3 flex min-h-[44px] items-center py-3 text-label text-muted underline hover:text-graphite"
            >{{ topic.title }}</NuxtLink>
          </li>
        </ul>
      </template>
    </ContentPolicyArticle>
  </div>
</template>
