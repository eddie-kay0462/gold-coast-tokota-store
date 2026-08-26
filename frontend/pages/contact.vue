<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'

/**
 * Linked from the product purchase panel ("Questions about fit? Contact Us"),
 * so this sits on a live path, not just in the footer.
 *
 * Reuses `FormsFeedbackForm` rather than growing a second contact form — it
 * already posts to `/feedback` and collects exactly name, email and message.
 */
const siteSettings = useSiteSettingsStore()
const { href: whatsappHref } = useWhatsApp()

useSeoMeta({
  title: 'Contact us — Gold Coast Tokota',
  description:
    'Questions about sizing, an order, or a custom pair? Message us on WhatsApp or send us a note.',
  ogTitle: 'Contact us — Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-10">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">Contact us</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          Questions about sizing, an order in progress, or a custom pair? We’re quickest on
          WhatsApp, but a note works just as well.
        </p>
      </header>

      <div class="flex w-full flex-col items-start gap-10 md:flex-row md:gap-12">
        <div class="flex w-full min-w-0 flex-1 flex-col items-start gap-5">
          <h2 class="w-full text-display-sm font-normal text-black">Send us a note</h2>
          <FormsFeedbackForm />
        </div>

        <div class="flex w-full min-w-0 flex-col items-start gap-5 md:w-[300px] md:shrink-0">
          <h2 class="w-full text-display-sm font-normal text-black">Other ways to reach us</h2>

          <CommonBrandButton v-if="whatsappHref" :to="whatsappHref" full>
            WhatsApp us
          </CommonBrandButton>

          <!-- Contact details are admin-editable (SiteSetting), so they render
               only once the store has been populated — same rule as the footer. -->
          <ul class="flex w-full flex-col items-start gap-2 text-label">
            <li v-if="siteSettings.contactEmail" class="w-full">
              <a :href="`mailto:${siteSettings.contactEmail}`" class="-my-3 flex min-h-[44px] items-center py-3 text-graphite underline hover:no-underline">
                {{ siteSettings.contactEmail }}
              </a>
            </li>
            <li v-if="siteSettings.contactPhone" class="w-full">
              <a :href="`tel:${siteSettings.contactPhone}`" class="-my-3 flex min-h-[44px] items-center py-3 text-graphite underline hover:no-underline">
                {{ siteSettings.contactPhone }}
              </a>
            </li>
          </ul>

          <div class="flex w-full flex-col items-start gap-2 border-t border-line pt-5">
            <p class="w-full text-caption text-muted">Looking for something specific?</p>
            <NuxtLink to="/help" class="-my-3 flex min-h-[44px] items-center py-3 text-label text-graphite underline hover:no-underline">Help Centre</NuxtLink>
            <NuxtLink to="/size-guide" class="-my-3 flex min-h-[44px] items-center py-3 text-label text-graphite underline hover:no-underline">Size guide</NuxtLink>
            <NuxtLink to="/help/bulk-orders" class="-my-3 flex min-h-[44px] items-center py-3 text-label text-graphite underline hover:no-underline">Bulk &amp; corporate orders</NuxtLink>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
