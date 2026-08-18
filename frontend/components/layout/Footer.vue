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
      { label: 'Environmental Initiatives', to: '/about#sustainability' },
      { label: 'Factories', to: '/about#factories' },
      { label: 'DEI', to: '/about#dei' },
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
  <footer class="flex w-full flex-col items-center bg-surface px-5 pt-10 lg:px-[72px]">
    <div class="flex w-full flex-col items-start lg:flex-row">
      <div
        v-for="column in columns"
        :key="column.heading"
        class="flex min-w-0 flex-1 flex-col items-start gap-5 p-5"
      >
        <h2 class="w-full text-body font-normal tracking-[0.2px] text-graphite">
          {{ column.heading }}
        </h2>
        <ul class="flex w-full flex-col items-start gap-2.5">
          <li v-for="link in column.links" :key="link.label" class="w-full">
            <a
              v-if="'external' in link && link.external"
              :href="link.to"
              target="_blank"
              rel="noopener noreferrer"
              class="text-label text-muted hover:text-graphite"
            >{{ link.label }}</a>
            <NuxtLink v-else :to="link.to" class="text-label text-muted hover:text-graphite">
              {{ link.label }}
            </NuxtLink>
          </li>
        </ul>
      </div>

      <div class="flex w-full items-start p-5 lg:w-auto">
        <FormsNewsletterForm source="footer" />
      </div>
    </div>

    <!-- Contact details are admin-editable (SiteSetting), so they render only
         once the store has been populated. -->
    <div
      v-if="siteSettings.contactEmail || siteSettings.contactPhone"
      class="flex w-full flex-wrap justify-center gap-x-6 gap-y-1 py-2 text-caption text-muted"
    >
      <a v-if="siteSettings.contactEmail" :href="`mailto:${siteSettings.contactEmail}`">
        {{ siteSettings.contactEmail }}
      </a>
      <a v-if="siteSettings.contactPhone" :href="`tel:${siteSettings.contactPhone}`">
        {{ siteSettings.contactPhone }}
      </a>
    </div>

    <div class="flex w-full flex-col items-center gap-4 py-4 text-center text-caption text-muted">
      <ul class="flex flex-wrap items-start justify-center gap-x-6 gap-y-2">
        <li v-for="link in legalLinks" :key="link.label" class="whitespace-nowrap">
          <NuxtLink :to="link.to" class="hover:text-graphite">{{ link.label }}</NuxtLink>
        </li>
      </ul>
      <p class="w-full">© Gold Coast Tokota {{ new Date().getFullYear() }} All Rights Reserved</p>
    </div>
  </footer>
</template>
