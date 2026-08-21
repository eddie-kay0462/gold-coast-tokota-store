<script setup lang="ts">
type ApiPage = { title: string, body: string }

const config = useRuntimeConfig()

// The About page is CMS-backed (Feature 9): the admin owns the opening
// manifesto, everything else is the designed brand story. A missing or failing
// endpoint falls back to the design's own copy rather than throwing — the same
// pattern the home, shop and blog pages use.
const { data: page } = await useAsyncData('about-page', () =>
  $fetch<{ data: ApiPage }>(`${config.public.apiBase}/pages/about`)
    .catch(() => null),
)

const statementBody = computed(() => page.value?.data?.body ?? null)

useSeoMeta({
  title: 'About — Gold Coast Tokota',
  description:
    'Exceptional quality, ethical factories, radical transparency — the story behind Gold Coast Tokota, handmade in Ghana.',
  ogTitle: 'About — Gold Coast Tokota',
  ogDescription: 'Exceptional quality. Ethical factories. Radical Transparency.',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <AboutSectionNav />

    <AboutHeroSection />

    <MotionGsapScrollReveal>
      <AboutStatementSection :body="statementBody" />
    </MotionGsapScrollReveal>

    <AboutFeatureSection
      id="factories"
      eyebrow="OUR FACTORIES"
      heading="Our ethical approach."
      body="We spend months finding the best factories around the world—the same ones that produce your favorite designer labels. We visit them often and build strong personal relationships with the owners. Each factory is given a compliance audit to evaluate factors like fair wages, reasonable hours, and environment. Our goal? A score of 90 or above for every factory."
      image="/design/about-factories.png"
      alt="An artisan hand-cutting leather at the workbench"
      :height="733"
    />

    <img
      src="/design/about-workshop-wide.png"
      alt="Inside the Gold Coast Tokota workshop, tools and hides laid out at the bench"
      class="aspect-[16/9] max-h-[637px] w-full object-cover"
      loading="lazy"
    >

    <AboutFeatureSection
      id="sustainability"
      eyebrow="OUR QUALITY"
      :heading="'Designed\nto last.'"
      body="We're not big on trends. We want you to wear our pieces for years, even decades, to come. That's why we source the finest materials and the most skilled hands for our timeless products—like our full-grain leather ahenema, hand-stitched soles, and locally woven kente trims."
      image="/design/about-quality.png"
      alt="A pair of blue and black leather sandals photographed from above"
      :reverse="true"
      :height="552"
    />

    <img
      src="/design/about-materials-wide.png"
      alt="Woven material and finishing supplies arranged across the workshop floor"
      class="aspect-[16/9] max-h-[560px] w-full object-cover"
      loading="lazy"
    >

    <AboutFeatureSection
      eyebrow="OUR PRICES"
      heading="Radically Transparent."
      body="We believe our customers have a right to know how much their shoes cost to make. We reveal the true costs behind all of our products—from materials to labor to transportation—then offer them to you, minus the traditional retail markup."
      image="/design/about-price-breakdown.png"
      alt="A cost breakdown comparing the Gold Coast Tokota price with traditional retail"
      :tinted="false"
      :contain="true"
      :height="660"
    />

    <MotionGsapScrollReveal>
      <AboutExploreGrid />
    </MotionGsapScrollReveal>
  </div>
</template>
