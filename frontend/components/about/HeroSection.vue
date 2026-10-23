<template>
  <!-- Redesigned 23 Oct. It was a stock photograph of someone folding a shirt
       under "Exceptional quality. Ethical factories. Radical Transparency.",
       none of which is this brand. Now: the brand phrase (GOLD_COAST_TOKOTA.md
       §24) on the site's deep black, beside the Ahotoso ahenema from the
       brand's own catalogue photography: black and gold, the brand's colours.
       The team photo that was here is the People section's now. -->
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
          src="/design/about-hero-ahotoso.webp"
          alt="The Ahotoso ahenema: black leather with gold crowns, handmade by Gold Coast Tokota"
          class="absolute inset-0 size-full bg-surface object-cover"
          width="1500"
          height="1667"
          fetchpriority="high"
        >
      </div>
    </div>
  </section>
</template>
