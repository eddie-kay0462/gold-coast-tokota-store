<script setup lang="ts">
import { whatsappMessage } from '~/utils/whatsapp'
import { SIZE_GUIDE_BETWEEN_SIZES, SIZE_GUIDE_STEPS } from '~/utils/sizeGuide'
/**
 * Linked from the contact and international pages. The product page opens the
 * same content in a modal instead (`ShopSizeGuideModal`), so a shopper isn't
 * taken away from the pair they're looking at. Both read `utils/sizeGuide.ts`.
 *
 * Bespoke rather than a CMS page: it is a measurement table, and a table is the
 * structured-UI half of the split rule.
 */

useSeoMeta({
  title: 'Size guide — Gold Coast Tokota',
  description:
    'How to measure your foot and find your size in Gold Coast Tokota sandals, with EU, UK and US conversions.',
  ogTitle: 'Size guide — Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-10">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">Size guide</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          Our sandals are handmade, so a pair sits a little differently from a factory shoe.
          Measure your foot before you order. It takes a minute and saves a return.
        </p>
      </header>

      <section class="flex w-full max-w-[720px] flex-col items-start gap-3">
        <h2 class="w-full text-display-sm font-normal text-black">How to measure</h2>
        <ol class="flex w-full list-decimal flex-col gap-2 pl-5 text-body text-graphite">
          <li v-for="step in SIZE_GUIDE_STEPS" :key="step">{{ step }}</li>
        </ol>
      </section>

      <section class="flex w-full flex-col items-start gap-3">
        <h2 class="w-full text-display-sm font-normal text-black">Conversions</h2>
        <ShopSizeChart />
        <p class="w-full text-caption text-muted">
          {{ SIZE_GUIDE_BETWEEN_SIZES }}
        </p>
      </section>

      <section class="flex w-full max-w-[720px] flex-col items-start gap-3 border-t border-line pt-10">
        <h2 class="w-full text-display-sm font-normal text-black">Still not sure?</h2>
        <p class="w-full text-body text-graphite">
          Send us your measurement and we’ll tell you which size to take. For a custom pair we
          work from your measurements directly, so no conversion is needed.
        </p>
        <div class="flex w-full flex-col items-stretch gap-3 pt-2 sm:flex-row sm:items-center">
          <CommonWhatsAppLink source="size-guide" variant="solid" :message="whatsappMessage.sizing()">
            Ask us on WhatsApp
          </CommonWhatsAppLink>
          <CommonBrandButton to="/booking" variant="white">Order a custom pair</CommonBrandButton>
        </div>
      </section>
    </section>
  </div>
</template>
