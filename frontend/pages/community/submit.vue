<script setup lang="ts">
import { whatsappMessage } from '~/utils/whatsapp'
import { useSiteSettingsStore } from '~/stores/siteSettings'

/**
 * Sharing a photo of your pair.
 *
 * This was an upload form that waited a moment and then said nothing could be
 * uploaded: there is no endpoint for customer photos. It now sends people to
 * where photos already arrive, Instagram (tag us) and WhatsApp (attach it in
 * the chat), and promises nothing the site can't do.
 */
const siteSettings = useSiteSettingsStore()

useSeoMeta({
  title: 'Share your photo | Gold Coast Tokota',
  description: 'Send us a photo of your Gold Coast Tokota sandals for a chance to be featured.',
  ogTitle: 'Share your photo | Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-8">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">Share your photo</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          We love seeing where our sandals end up. Tag us on Instagram with #GoldCoastTokota, or
          send the photo to us on WhatsApp, and we may feature it on the site. We’ll always ask you
          first.
        </p>
      </header>

      <div class="flex w-full flex-col items-stretch gap-3 sm:flex-row sm:items-start">
        <CommonWhatsAppLink source="community-photo" :message="whatsappMessage.photoShare()" class="sm:w-[240px]">
          Send it on WhatsApp
        </CommonWhatsAppLink>
        <CommonBrandButton v-if="siteSettings.instagramUrl" :to="siteSettings.instagramUrl">
          Tag us on Instagram
        </CommonBrandButton>
      </div>

      <p class="w-full max-w-[720px] text-caption text-muted">
        By sending a photo you confirm it’s yours to share. We’ll ask before we publish it, and
        you can tell us to remove it at any time.
      </p>
    </section>
  </div>
</template>
