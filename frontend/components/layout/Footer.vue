<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'

const siteSettings = useSiteSettingsStore()

const columns = computed(() => [
  {
    heading: 'Account',
    links: [
      { label: 'Log In', to: '/account/login' },
      { label: 'Sign Up', to: '/account/register' },
      { label: 'Redeem a Gift Card', to: '/gift-cards' },
    ],
  },
  {
    heading: 'Company',
    links: [
      { label: 'About', to: '/about' },
      { label: 'Environmental Initiatives', to: '/sustainability' },
      { label: 'Factories', to: '/about#factories' },
      // Points at /careers#dei, not /about#dei: the About page has never had a
      // `dei` anchor, and DEI is a people-and-culture topic that belongs with
      // careers. See FOR_THE_TEAM.md if the brand wants it back on /about.
      { label: 'DEI', to: '/careers#dei' },
      { label: 'Careers', to: '/careers' },
      { label: 'International', to: '/international' },
      { label: 'Accessibility', to: '/accessibility' },
    ],
  },
  {
    heading: 'Get Help',
    links: [
      { label: 'Help Center', to: '/help' },
      { label: 'Return Policy', to: '/help/returns' },
      { label: 'Shipping Info', to: '/help/shipping' },
      { label: 'Bulk Orders', to: '/help/bulk-orders' },
    ],
  },
  {
    heading: 'Connect',
    // Social destinations come from the admin-editable SiteSetting record so
    // the owner can repoint them without a deploy; only Instagram is modelled
    // in the spec, so the rest stay as internal routes for now.
    links: [
      { label: 'Facebook', to: '/', external: false },
      { label: 'Instagram', to: siteSettings.instagramUrl || '/', external: !!siteSettings.instagramUrl },
      { label: 'Twitter', to: '/', external: false },
      { label: 'Affiliates', to: '/affiliates', external: false },
      { label: 'Our Stores', to: '/stores', external: false },
    ],
  },
])

const legalLinks = [
  { label: 'Privacy Policy', to: '/legal/privacy' },
  { label: 'Terms of Service', to: '/legal/terms' },
  { label: 'Do Not Sell or Share My Personal Information', to: '/legal/do-not-sell' },
  { label: 'CS Supply Chain Transparency', to: '/legal/supply-chain' },
  { label: 'Vendor Code of Conduct', to: '/legal/vendor-code' },
  { label: 'Sitemap Pages', to: '/sitemap.xml' },
  { label: 'Sitemap Products', to: '/sitemap.xml' },
]
</script>

<template>
  <!-- The footer moved to the dark chrome ground with the approved Template B
       mockup. `chrome-dark` switches the focus ring to white, since the base
       graphite ring is invisible here. -->
  <footer class="chrome-dark flex w-full flex-col items-center bg-chrome px-5 pt-12 text-white md:px-10 lg:px-[72px]">
    <!-- Brand column first at `lg`, as the mockup draws it, with the four link
         sets beside it. The newsletter lives inside the brand column rather
         than as a sixth track: five tracks already squeeze the link labels, and
         the mockup gives the sign-up the same generous width as the brand. -->
    <div class="grid w-full gap-x-10 gap-y-10 md:grid-cols-2 lg:grid-cols-[1.5fr_repeat(4,minmax(0,1fr))]">
      <div class="flex flex-col items-start gap-5">
        <!-- `min-h-[44px]` keeps the tap target legal: the logo itself is 32px
             tall, and a link wrapping it alone is under the floor. -->
        <NuxtLink to="/" class="-my-1.5 flex min-h-[44px] items-center py-1.5" aria-label="Gold Coast Tokota — home">
          <img
            src="/brand/logo-white.png"
            alt="Gold Coast Tokota"
            class="h-8 w-auto"
            width="435"
            height="108"
            loading="lazy"
          >
        </NuxtLink>

        <p class="max-w-[300px] text-label text-white/60">
          Handcrafted sandals and ahenema, made in Ghana from leather, velvet and
          upcycled materials.
        </p>

        <!-- Contact details are admin-editable (SiteSetting), so they render
             only once the store has been populated. -->
        <address
          v-if="siteSettings.contactEmail || siteSettings.contactPhone"
          class="flex flex-col items-start gap-1 not-italic text-caption text-white/60"
        >
          <a
            v-if="siteSettings.contactPhone"
            :href="`tel:${siteSettings.contactPhone}`"
            class="-my-2 flex min-h-[44px] items-center py-2 hover:text-white"
          >{{ siteSettings.contactPhone }}</a>
          <a
            v-if="siteSettings.contactEmail"
            :href="`mailto:${siteSettings.contactEmail}`"
            class="-my-2 flex min-h-[44px] items-center py-2 hover:text-white"
          >{{ siteSettings.contactEmail }}</a>
        </address>

        <div id="newsletter" class="w-full scroll-mt-24">
          <h2 class="mb-3 w-full text-eyebrow uppercase tracking-[0.6px] text-white/50">
            Newsletter
          </h2>
          <FormsNewsletterForm source="footer" tone="dark" />
        </div>
      </div>

      <div
        v-for="column in columns"
        :key="column.heading"
        class="flex min-w-0 flex-col items-start gap-4"
      >
        <h2 class="w-full text-eyebrow uppercase tracking-[0.6px] text-white/50">
          {{ column.heading }}
        </h2>
        <ul class="flex w-full flex-col items-start gap-1.5">
          <li v-for="link in column.links" :key="link.label" class="w-full">
            <a
              v-if="'external' in link && link.external"
              :href="link.to"
              target="_blank"
              rel="noopener noreferrer"
              class="-my-2.5 flex min-h-[44px] items-center py-2.5 text-label text-white/70 hover:text-white"
            >{{ link.label }}</a>
            <NuxtLink
              v-else
              :to="link.to"
              class="-my-2.5 flex min-h-[44px] items-center py-2.5 text-label text-white/70 hover:text-white"
            >
              {{ link.label }}
            </NuxtLink>
          </li>
        </ul>
      </div>
    </div>

    <!-- Bottom bar behind a hairline, as the mockup draws it. The tall bottom
         padding below `sm` reserves the corner the fixed WhatsApp button sits
         in, so the last row of links is never underneath it. -->
    <div class="mt-10 flex w-full flex-col items-center gap-4 border-t border-white/10 py-6 pb-[calc(1.5rem+4.5rem)] text-center text-caption text-white/45 sm:pb-6">
      <ul class="flex flex-wrap items-start justify-center gap-x-6 gap-y-1">
        <li v-for="link in legalLinks" :key="link.label" class="whitespace-nowrap">
          <NuxtLink :to="link.to" class="-my-3 flex min-h-[44px] items-center py-3 hover:text-white">
            {{ link.label }}
          </NuxtLink>
        </li>
      </ul>
      <div class="flex w-full flex-col items-center justify-between gap-1 sm:flex-row">
        <p>© Gold Coast Tokota {{ new Date().getFullYear() }} All Rights Reserved</p>
        <p>goldcoasttokota.store</p>
      </div>
    </div>
  </footer>
</template>
