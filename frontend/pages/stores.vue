<script setup lang="ts">
import { whatsappMessage } from '~/utils/whatsapp'
import { useSiteSettingsStore } from '~/stores/siteSettings'

/**
 * Where to find us in person.
 *
 * The workshop address is not in `SiteSetting` (it holds a contact email and
 * phone, not a street address), and inventing a location for a real business
 * would be worse than saying we don't list one yet. Contact details render from
 * the store when present, exactly as the footer does; the visit path routes
 * through the booking flow, which is a real, built page.
 */
const siteSettings = useSiteSettingsStore()

useSeoMeta({
  title: 'Our stores — Gold Coast Tokota',
  description: 'Visit the Gold Coast Tokota workshop in Accra, or find us at a market or event.',
  ogTitle: 'Our stores — Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-10">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">Find us</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          Everything we sell is made in our workshop in Accra. You’re welcome to visit — it’s
          worth seeing a pair being made.
        </p>
      </header>

      <div class="flex w-full flex-col items-start gap-10 md:flex-row md:gap-12">
        <div class="flex w-full min-w-0 flex-1 flex-col items-start gap-3">
          <h2 class="w-full text-display-sm font-normal text-black">The workshop</h2>
          <p class="w-full text-body text-graphite">
            Accra, Ghana. Visits are by arrangement so someone is free to show you around rather
            than you arriving mid-run.
          </p>
          <ul class="flex w-full flex-col items-start gap-1 pt-2 text-label">
            <li v-if="siteSettings.contactPhone" class="w-full">
              <a :href="`tel:${siteSettings.contactPhone}`" class="-my-3 flex min-h-[44px] items-center py-3 text-graphite underline hover:no-underline">
                {{ siteSettings.contactPhone }}
              </a>
            </li>
            <li v-if="siteSettings.contactEmail" class="w-full">
              <a :href="`mailto:${siteSettings.contactEmail}`" class="-my-3 flex min-h-[44px] items-center py-3 text-graphite underline hover:no-underline">
                {{ siteSettings.contactEmail }}
              </a>
            </li>
          </ul>
          <div class="flex w-full flex-col items-stretch gap-3 pt-2 sm:flex-row sm:items-start">
            <CommonBrandButton to="/booking">Book a visit</CommonBrandButton>
            <CommonWhatsAppLink source="stores" :message="whatsappMessage.visit()">
              Ask for directions
            </CommonWhatsAppLink>
          </div>
        </div>

        <div class="flex w-full min-w-0 flex-col items-start gap-3 md:w-[320px] md:shrink-0">
          <h2 class="w-full text-display-sm font-normal text-black">Stockists</h2>
          <p class="w-full text-caption text-muted">
            We don’t list retail stockists yet. If you run a shop and would like to carry us, we
            take wholesale orders.
          </p>
          <NuxtLink to="/help/bulk-orders" class="-my-3 flex min-h-[44px] items-center py-3 text-label text-graphite underline hover:no-underline">
            Wholesale &amp; bulk orders
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
