<script setup lang="ts">
/** Ordering from outside Ghana. Complements /help/shipping rather than repeating it. */
useSeoMeta({
  title: 'International orders — Gold Coast Tokota',
  description:
    'We ship Ghanaian-made sandals worldwide with DHL. How currency, duties and delivery work when you order from outside Ghana.',
  ogTitle: 'International orders — Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-10">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">Ordering from outside Ghana</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          We ship worldwide. Everything is still made by hand in Accra — it just takes a little
          longer to reach you.
        </p>
      </header>

      <div class="grid w-full grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col items-start gap-2 border border-line p-5">
          <h2 class="w-full text-display-sm font-normal text-black">Currency</h2>
          <p class="w-full text-caption text-muted">
            Prices are set in Ghana cedis and shown in dollars using the current rate. Switch
            currency any time with the toggle in the header. The rate is locked when you pay.
          </p>
        </div>
        <div class="flex min-w-0 flex-col items-start gap-2 border border-line p-5">
          <h2 class="w-full text-display-sm font-normal text-black">Shipping</h2>
          <p class="w-full text-caption text-muted">
            International orders travel with DHL, tracked door to door. Transit time is shown at
            checkout before you pay.
          </p>
        </div>
        <div class="flex min-w-0 flex-col items-start gap-2 border border-line p-5">
          <h2 class="w-full text-display-sm font-normal text-black">Duties and taxes</h2>
          <p class="w-full text-caption text-muted">
            Any import duty your country charges is payable by you and isn’t included in our
            price. DHL will contact you if anything is due.
          </p>
        </div>
      </div>

      <section class="flex w-full max-w-[720px] flex-col items-start gap-3 border-t border-line pt-10">
        <h2 class="w-full text-display-sm font-normal text-black">Sizing from a distance</h2>
        <p class="w-full text-body text-graphite">
          You can’t try a pair on before it ships, so measure your foot and check the size guide.
          If you’re between sizes, tell us and we’ll advise.
        </p>
        <div class="flex w-full flex-col items-stretch gap-3 pt-2 sm:flex-row sm:items-start">
          <CommonBrandButton to="/size-guide">Size guide</CommonBrandButton>
          <CommonBrandButton to="/help/shipping" variant="white">Shipping details</CommonBrandButton>
        </div>
      </section>
    </section>
  </div>
</template>
