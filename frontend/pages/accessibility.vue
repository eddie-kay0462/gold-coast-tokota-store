<script setup lang="ts">
import { POLICY_DRAFTS } from '~/utils/policyContent'
import { whatsappMessage } from '~/utils/whatsapp'

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
    <ContentPolicyArticle slug="accessibility">
      <template #footer>
        <!-- The article asks people to tell us when something is hard to use.
             Asking that of someone who is already struggling with the page, and
             then offering only a form, is the wrong way round. -->
        <div class="flex w-full flex-col items-start gap-2 border border-line bg-surface p-4">
          <p class="text-caption text-muted">
            Found something hard to use? Tell us the fastest way you can.
          </p>
          <CommonWhatsAppLink
            source="contact"
            :message="whatsappMessage.general()"
          >
            Report it on WhatsApp
          </CommonWhatsAppLink>
        </div>
      </template>
    </ContentPolicyArticle>
  </div>
</template>
