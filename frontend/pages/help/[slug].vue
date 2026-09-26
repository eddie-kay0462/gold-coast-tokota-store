<script setup lang="ts">
import { HELP_SLUGS, HELP_TOPICS, POLICY_DRAFTS, type HelpSlug } from '~/utils/policyContent'
import { whatsappMessage, type WhatsAppSource } from '~/utils/whatsapp'

/** Help articles. `/returns` and `/shipping` 301 here — see nuxt.config.ts. */
definePageMeta({
  validate: (route) => HELP_SLUGS.includes(route.params.slug as HelpSlug),
})

const route = useRoute()
const slug = computed(() => route.params.slug as HelpSlug)
const draft = computed(() => POLICY_DRAFTS[slug.value]!)

/**
 * Every one of these articles tells the reader to message on WhatsApp and, until
 * now, gave them nothing to tap — the returns article most pointedly, since the
 * brand guidelines say a return or exchange is *initiated* on WhatsApp, with the
 * number printed underneath.
 *
 * The message matches the article, so the business does not have to open the
 * chat and ask what it is about.
 */
const WHATSAPP_CTA: Partial<Record<HelpSlug, { source: WhatsAppSource, label: string, message: string }>> = {
  returns: {
    source: 'returns',
    label: 'Start a return on WhatsApp',
    message: whatsappMessage.returns(),
  },
  shipping: {
    source: 'shipping',
    label: 'Ask about delivery on WhatsApp',
    message: whatsappMessage.shipping(),
  },
  'bulk-orders': {
    source: 'bulk-orders',
    label: 'Request a bulk quote on WhatsApp',
    message: whatsappMessage.bulkOrder(),
  },
}

const cta = computed(() => WHATSAPP_CTA[slug.value] ?? null)

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
        <div v-if="cta" class="mb-6 flex w-full flex-col items-start gap-2 border border-line bg-surface p-4">
          <p class="text-caption text-muted">
            Fastest way to sort this out — we reply during business hours.
          </p>
          <CommonWhatsAppLink :source="cta.source" :message="cta.message">
            {{ cta.label }}
          </CommonWhatsAppLink>
        </div>

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
