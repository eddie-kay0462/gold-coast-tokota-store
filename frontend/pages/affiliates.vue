<script setup lang="ts">
/**
 * Affiliate / creator partnerships.
 *
 * There is no affiliate platform wired up, so the page explains the programme
 * and routes applications through the contact form rather than pretending a
 * sign-up flow exists.
 */
useSeoMeta({
  title: 'Affiliates — Gold Coast Tokota',
  description:
    'Partner with Gold Coast Tokota — commission for creators and writers who share Ghanaian craft.',
  ogTitle: 'Affiliates — Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-10">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">Affiliates</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          If you write about African design, craft or footwear and your readers would wear our
          sandals, we’d like to work with you.
        </p>
      </header>

      <section class="flex w-full max-w-[720px] flex-col items-start gap-3">
        <h2 class="w-full text-display-sm font-normal text-black">How it works</h2>
        <ol class="flex w-full list-decimal flex-col gap-2 pl-5 text-body text-graphite">
          <li>Tell us about your audience and where you publish.</li>
          <li>We agree a commission rate and give you a tracking link.</li>
          <li>You earn on every order placed through it.</li>
        </ol>
        <p class="w-full text-caption text-muted">
          Rates depend on audience and format, so we set them case by case rather than publishing
          one number.
        </p>
      </section>

      <section class="flex w-full max-w-[720px] flex-col items-start gap-3 border-t border-line pt-10">
        <h2 class="w-full text-display-sm font-normal text-black">What we ask</h2>
        <ul class="flex w-full list-disc flex-col gap-2 pl-5 text-body text-graphite">
          <li>Say plainly that a link is an affiliate link. Your readers deserve that.</li>
          <li>Don’t bid on our brand name in paid search.</li>
          <li>Show the sandals as they are — we won’t ask you to overstate them.</li>
        </ul>
      </section>

      <section class="flex w-full max-w-[720px] flex-col items-start gap-3 border-t border-line pt-10">
        <h2 class="w-full text-display-sm font-normal text-black">Apply</h2>
        <p class="w-full text-body text-graphite">
          Send us a note with a link to your work and a sentence about your audience.
        </p>
        <CommonBrandButton to="/contact">Get in touch</CommonBrandButton>
      </section>
    </section>
  </div>
</template>
