<template>
  <!--
    Mission and vision. The heading was "We're on a mission to clean up a dirty
    industry", template copy; since the 23 Oct redesign it is the brand's own
    line (GOLD_COAST_TOKOTA.md §6, "Confident"). `#sustainability` moved to the
    materials section, which is what the home banner's link is about.

    What did not come across is that masthead's `Gold Coast Tokota` wordmark set
    at `display-brand`. A page has one masthead, and About already has one; a
    second brand-sized wordmark two thirds of the way down read as the start of
    a different page. Dropping it also retires open issue #9, which was about
    that token's very airy leading at the mobile floor.
  -->
  <section
    id="mission"
    class="page-gutter section-y mx-auto flex w-full max-w-[1440px] scroll-mt-8 flex-col items-start gap-8"
  >
    <div class="flex w-full flex-col items-start gap-2">
      <div class="h-[14px] w-full bg-ink" />
      <h2 class="w-full font-normal text-display-heading text-black">
        Where we’re going.
      </h2>
      <p class="w-full font-light text-lede text-subtle">
        A future where African manufacturing creates value for people, culture and the planet.
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
