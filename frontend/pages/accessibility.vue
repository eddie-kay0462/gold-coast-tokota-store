<script setup lang="ts">
import { POLICY_DRAFTS } from '~/utils/policyContent'

/**
 * A flat URL rather than a `[slug]` route, because the footer already commits
 * to `/accessibility` and this is a single page — the CMS template does the
 * work, this file just names the slug.
 */
const draft = POLICY_DRAFTS.accessibility!

useSeoMeta({
  title: `${draft.title} — Gold Coast Tokota`,
  description: draft.summary,
  ogTitle: `${draft.title} — Gold Coast Tokota`,
  ogDescription: draft.summary,
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <ContentPolicyArticle slug="accessibility" />
  </div>
</template>
