<template>
  <!-- The people. Facts from Business Beat (Feb 2025, fifteen workers including
       young people, women and people living with disabilities) and the brand's
       Tyred of Waste post (training for artisans with disabilities); names and
       roles from GOLD_COAST_TOKOTA.md §17. The photo is 544px wide, so it's held
       to that rather than stretched full-bleed. -->
  <section id="people" class="page-gutter section-y mx-auto grid w-full max-w-[1440px] scroll-mt-8 grid-cols-1 items-center gap-10 md:grid-cols-2 lg:gap-16">
    <img
      src="/design/news-tyred.png"
      alt="An artisan in a wheelchair working on sandals at the Gold Coast Tokota workshop, with the team behind him"
      class="aspect-square w-full max-w-[544px] object-cover md:justify-self-end"
      width="544"
      height="564"
      loading="lazy"
    >
    <div class="flex flex-col items-start gap-4">
      <p class="caps-label text-graphite">The people</p>
      <h2 class="text-display-section font-light text-ink">Made by hand, by artisans we train.</h2>
      <p class="max-w-[520px] text-body font-light text-graphite">
        Our workshop trains and employs young people, women and people living with
        disabilities as skilled artisans. Every pair passes through their hands, from the
        first cut of the sole to the last stitch.
      </p>
      <dl class="mt-2 grid w-full max-w-[520px] grid-cols-1 gap-4 border-t border-line pt-5 sm:grid-cols-2">
        <div>
          <dt class="text-caption text-muted">Founder &amp; CEO</dt>
          <dd class="text-label text-ink">Samuel Kumi-Gyau</dd>
        </div>
        <div>
          <dt class="text-caption text-muted">Operations Lead</dt>
          <dd class="text-label text-ink">Mary Seade</dd>
        </div>
      </dl>
    </div>
  </section>
</template>
