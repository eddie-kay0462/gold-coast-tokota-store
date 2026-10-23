<template>
  <!-- Redesigned 23 Oct. It was a stock photograph of someone folding a shirt
       under "Exceptional quality. Ethical factories. Radical Transparency.",
       none of which is this brand. Now: the brand phrase (GOLD_COAST_TOKOTA.md
       §24) on the site's deep black, beside the brand's own photo of the team
       with its FITA Autotech tyre partners. -->
  <section class="w-full bg-ink text-white">
    <div class="mx-auto grid w-full max-w-[1440px] grid-cols-1 items-stretch md:grid-cols-2">
      <div class="page-gutter flex flex-col justify-center gap-6 py-16 md:py-24 lg:py-32">
        <p class="caps-label text-white">About Gold Coast Tokota</p>
        <h1 class="text-display-xl font-light text-white">
          Crafted with purpose.<br>Inspired by culture.
        </h1>
        <p class="max-w-[520px] text-lede font-light text-white">
          We turn discarded tyres, water sachets and old denim into handcrafted
          ahenema and sandals in Haatso, Accra, and invite people into the
          workshop to see how it’s done.
        </p>
        <div class="flex flex-col items-stretch gap-3 pt-2 sm:flex-row sm:items-start">
          <CommonBrandButton to="/shop" variant="white">Shop the collection</CommonBrandButton>
          <CommonBrandButton to="/booking" variant="white">Visit the workshop</CommonBrandButton>
        </div>
      </div>

      <div class="relative min-h-[360px] md:min-h-[560px]">
        <img
          src="/design/post-tyred-hero.png"
          alt="The Gold Coast Tokota team with the FITA Autotech tyre partners in the workshop, under the brand’s Africa map"
          class="absolute inset-0 size-full object-cover object-top"
          width="1529"
          height="1536"
          fetchpriority="high"
        >
      </div>
    </div>
  </section>
</template>
