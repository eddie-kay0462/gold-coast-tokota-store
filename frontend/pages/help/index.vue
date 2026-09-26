<script setup lang="ts">
import { whatsappMessage } from '~/utils/whatsapp'
import { HELP_TOPICS } from '~/utils/policyContent'

/**
 * The help hub. Bespoke rather than a `[slug]` page because it is a directory
 * of topics, not an article — the prose-vs-structured-UI split rule.
 *
 * Topics come from `HELP_TOPICS`, which is derived from the same drafts the
 * articles render, so the hub and its pages cannot drift apart.
 */
// `href` is null when the admin hasn't set a number — the CTA hides rather
// than linking to an invalid wa.me URL.

useSeoMeta({
  title: 'Help Centre — Gold Coast Tokota',
  description:
    'Returns, shipping and bulk orders — everything you need to know about ordering from Gold Coast Tokota.',
  ogTitle: 'Help Centre — Gold Coast Tokota',
  ogDescription: 'Returns, shipping, bulk orders and how to reach us.',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-10">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">Help Centre</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          Answers to the questions we’re asked most. If yours isn’t here, message us —
          we reply fastest on WhatsApp.
        </p>
      </header>

      <ul class="grid w-full grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <li v-for="topic in HELP_TOPICS" :key="topic.slug" class="min-w-0">
          <NuxtLink
            :to="`/help/${topic.slug}`"
            class="group flex h-full w-full flex-col items-start gap-2 border border-line p-5 hover:border-graphite"
          >
            <h2 class="w-full text-display-sm font-normal text-black group-hover:underline">
              {{ topic.title }}
            </h2>
            <p class="w-full text-caption text-muted">{{ topic.summary }}</p>
          </NuxtLink>
        </li>
      </ul>

      <div class="flex w-full flex-col items-start gap-4 border-t border-line pt-10">
        <h2 class="w-full text-display-sm font-normal text-black">Still need a hand?</h2>
        <p class="w-full max-w-[720px] text-body text-graphite">
          Tell us your order number and what’s wrong, and we’ll sort it.
        </p>
        <div class="flex w-full flex-col items-stretch gap-3 sm:flex-row sm:items-start">
          <CommonBrandButton to="/contact">Contact us</CommonBrandButton>
          <CommonWhatsAppLink source="contact" :message="whatsappMessage.general()">
            WhatsApp us
          </CommonWhatsAppLink>
        </div>
      </div>
    </section>
  </div>
</template>
