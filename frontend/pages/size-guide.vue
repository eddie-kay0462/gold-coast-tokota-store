<script setup lang="ts">
import { whatsappMessage } from '~/utils/whatsapp'
/**
 * Linked twice from the product purchase panel, so this is a live path.
 *
 * Bespoke rather than a CMS page: it is a measurement table, and a table is the
 * structured-UI half of the split rule.
 *
 * NOTE: the conversions below are the standard EU/UK/US ladder, not measured
 * Gold Coast Tokota lasts. Confirm against real production lasts before launch —
 * a size chart that is wrong by half a size causes returns.
 */
const rows = [
  { eu: '38', uk: '5', us: '6', cm: '24.0' },
  { eu: '39', uk: '6', us: '7', cm: '24.7' },
  { eu: '40', uk: '6.5', us: '7.5', cm: '25.3' },
  { eu: '41', uk: '7.5', us: '8.5', cm: '26.0' },
  { eu: '42', uk: '8', us: '9', cm: '26.7' },
  { eu: '43', uk: '9', us: '10', cm: '27.3' },
  { eu: '44', uk: '9.5', us: '10.5', cm: '28.0' },
  { eu: '45', uk: '10.5', us: '11.5', cm: '28.7' },
  { eu: '46', uk: '11', us: '12', cm: '29.3' },
]

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
          Measure your foot before you order — it takes a minute and saves a return.
        </p>
      </header>

      <section class="flex w-full max-w-[720px] flex-col items-start gap-3">
        <h2 class="w-full text-display-sm font-normal text-black">How to measure</h2>
        <ol class="flex w-full list-decimal flex-col gap-2 pl-5 text-body text-graphite">
          <li>Stand on a sheet of paper with your heel against a wall.</li>
          <li>Mark the paper at the tip of your longest toe.</li>
          <li>Measure from the wall edge to the mark, in centimetres.</li>
          <li>Measure late in the day — feet swell, and an evening measurement is the honest one.</li>
          <li>If your feet differ, use the larger.</li>
        </ol>
      </section>

      <section class="flex w-full flex-col items-start gap-3">
        <h2 class="w-full text-display-sm font-normal text-black">Conversions</h2>
        <!-- The table is wider than a 320px viewport, so it scrolls inside its
             own container rather than pushing the document sideways. -->
        <div class="w-full overflow-x-auto border border-line">
          <table class="w-full min-w-[420px] border-collapse text-left">
            <caption class="sr-only">Foot length in centimetres against EU, UK and US sizes</caption>
            <thead>
              <tr class="border-b border-line bg-surface">
                <th scope="col" class="px-4 py-3 text-caption font-normal text-graphite">Foot length</th>
                <th scope="col" class="px-4 py-3 text-caption font-normal text-graphite">EU</th>
                <th scope="col" class="px-4 py-3 text-caption font-normal text-graphite">UK</th>
                <th scope="col" class="px-4 py-3 text-caption font-normal text-graphite">US</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.eu" class="border-b border-line last:border-b-0">
                <th scope="row" class="px-4 py-3 text-body font-light text-black">{{ row.cm }} cm</th>
                <td class="px-4 py-3 text-body text-graphite">{{ row.eu }}</td>
                <td class="px-4 py-3 text-body text-graphite">{{ row.uk }}</td>
                <td class="px-4 py-3 text-body text-graphite">{{ row.us }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="w-full text-caption text-muted">
          Between sizes? Take the larger — an ahenema should sit with a little room at the toe.
        </p>
      </section>

      <section class="flex w-full max-w-[720px] flex-col items-start gap-3 border-t border-line pt-10">
        <h2 class="w-full text-display-sm font-normal text-black">Still not sure?</h2>
        <p class="w-full text-body text-graphite">
          Send us your measurement and we’ll tell you which size to take. For a custom pair we
          work from your measurements directly — no conversion needed.
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
