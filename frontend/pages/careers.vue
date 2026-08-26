<script setup lang="ts">
/**
 * Careers, and the home of the `#dei` anchor.
 *
 * The footer's "DEI" link used to point at `/about#dei`, an anchor that has
 * never existed on that page. It points here instead: DEI is a people-and-
 * culture topic, this page is about working here, and repointing avoided
 * inventing brand DEI copy for the About page. If the brand would rather it
 * live on /about, that is a content decision — see FOR_THE_TEAM.md.
 *
 * There are no open roles data source yet; when hiring is real this list should
 * come from the CMS rather than being hardcoded here.
 */
useSeoMeta({
  title: 'Careers — Gold Coast Tokota',
  description:
    'Work with us in Accra — making, designing and running a Ghanaian footwear brand.',
  ogTitle: 'Careers — Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-10">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">Careers</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          We’re a small team in Accra making sandals by hand. When we hire, we hire for craft and
          for care — the rest can be taught.
        </p>
      </header>

      <section class="flex w-full max-w-[720px] flex-col items-start gap-3">
        <h2 class="w-full text-display-sm font-normal text-black">Open roles</h2>
        <div class="flex w-full flex-col items-start gap-4 border border-line p-6">
          <p class="w-full text-body text-graphite">
            No open roles right now.
          </p>
          <p class="w-full text-caption text-muted">
            We keep a list of people we’d like to work with. If that’s you, send us a note about
            what you make and we’ll be in touch when something opens up.
          </p>
          <CommonBrandButton to="/contact">Introduce yourself</CommonBrandButton>
        </div>
      </section>

      <!-- The footer's DEI link resolves here. `scroll-mt` clears the sticky
           header so the heading isn't hidden underneath it on arrival. -->
      <section id="dei" class="flex w-full max-w-[720px] scroll-mt-24 flex-col items-start gap-3 border-t border-line pt-10">
        <h2 class="w-full text-display-sm font-normal text-black">
          Diversity, equity and inclusion
        </h2>
        <p class="w-full text-body text-graphite">
          We hire from the communities we work in, and we pay the same rate for the same work
          regardless of who is doing it.
        </p>
        <p class="w-full text-body text-graphite">
          Most of our team learned the craft here rather than arriving with it, and we would
          rather train someone than import a finished skill. That is the practical shape our
          commitment takes.
        </p>
        <p class="w-full text-caption text-muted">
          This section is a placeholder written to give the page its shape, and has not been
          approved as a statement of company policy.
        </p>
      </section>

      <section class="flex w-full max-w-[720px] flex-col items-start gap-3 border-t border-line pt-10">
        <h2 class="w-full text-display-sm font-normal text-black">Working here</h2>
        <ul class="flex w-full list-disc flex-col gap-2 pl-5 text-body text-graphite">
          <li>Based at our workshop in Accra, not remote — the work is physical.</li>
          <li>Training is on the job, alongside people who have done it for years.</li>
          <li>We are closed on public holidays and we mean it.</li>
        </ul>
      </section>
    </section>
  </div>
</template>
