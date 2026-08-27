<template>
  <!--
    The opening of the sustainability programme, folded into About when the two
    pages merged (27 Aug). It carries the mission copy from the old
    `/sustainability` masthead (Figma 10:915) — the heavy rule and the "clean up
    a dirty industry" statement.

    What did not come across is that masthead's `Gold Coast Tokota` wordmark set
    at `display-brand`. A page has one masthead, and About already has one; a
    second brand-sized wordmark two thirds of the way down read as the start of
    a different page. Dropping it also retires open issue #9, which was about
    that token's very airy leading at the mobile floor.
  -->
  <section
    id="sustainability"
    class="page-gutter section-y mx-auto flex w-full max-w-[1440px] scroll-mt-8 flex-col items-start gap-8"
  >
    <div class="flex w-full flex-col items-start gap-2">
      <div class="h-[14px] w-full bg-graphite" />
      <h2 class="w-full font-normal text-display-heading text-black">
        We're on a mission to clean up a dirty industry.
      </h2>
      <p class="w-full font-light text-lede text-subtle">
        These are the people, stories, and ideas that will help us get there.
      </p>
    </div>

    <!-- Mission and vision, verbatim from the brand guidelines. They had no home
         anywhere on the storefront before this, and the merged page is the one
         place a reader is already asking what the company is for. -->
    <div class="grid w-full gap-8 border-t border-line pt-8 md:grid-cols-2 md:gap-12">
      <div class="flex flex-col items-start gap-3">
        <h3 class="text-eyebrow uppercase tracking-[0.6px] text-muted">Our mission</h3>
        <p class="text-body font-light text-graphite">
          To reshape manufacturing in Africa by transforming waste into premium
          handcrafted footwear, preserving Ghanaian craftsmanship, empowering
          young people through skills development and creating sustainable
          cultural experiences that generate lasting social, environmental and
          economic impact.
        </p>
      </div>

      <div class="flex flex-col items-start gap-3">
        <h3 class="text-eyebrow uppercase tracking-[0.6px] text-muted">Our vision</h3>
        <p class="text-body font-light text-graphite">
          To become Africa's leading sustainable footwear and cultural tourism
          enterprise, inspiring a future where African craftsmanship, circular
          manufacturing and cultural heritage drive inclusive economic growth and
          global recognition.
        </p>
      </div>
    </div>
  </section>
</template>
