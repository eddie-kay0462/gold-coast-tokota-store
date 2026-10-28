<script setup lang="ts">
import { whatsappMessage } from '~/utils/whatsapp'

/**
 * Gift cards, for now arranged by hand on WhatsApp.
 *
 * Until 28 Oct 2026 this page had a "Check balance" form that waited a moment
 * and then said gift cards couldn't be used yet, under a warning that they
 * weren't live. Online gift cards (bought through Paystack, spent at checkout,
 * shared with store credit) are planned after launch; until then the page says
 * plainly how a gift is arranged today.
 */
useSeoMeta({
  title: 'Gift cards | Gold Coast Tokota',
  description: 'Give a pair of handmade Ghanaian sandals with a Gold Coast Tokota gift card.',
  ogTitle: 'Gift cards | Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-8">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">Gift cards</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          Let them choose their own pair, and their own size, which is the part that usually
          goes wrong.
        </p>
        <p class="w-full max-w-[720px] text-body text-graphite">
          Gift cards are arranged on WhatsApp. Tell us the amount and who it’s for, and we’ll
          send a card you can pass on. If you were given one, message us with the code when you
          order.
        </p>
      </header>

      <div class="flex w-full flex-col items-stretch gap-3 sm:flex-row sm:items-start">
        <CommonWhatsAppLink source="gift-cards" :message="whatsappMessage.giftEnquiry()" class="sm:w-[240px]">
          Arrange a gift
        </CommonWhatsAppLink>
        <CommonBrandButton to="/contact">Contact us</CommonBrandButton>
      </div>
    </section>
  </div>
</template>
