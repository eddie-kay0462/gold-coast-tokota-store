<script setup lang="ts">
import { formatPolicyDate } from '~/utils/policyContent'

/**
 * The shared body for every prose page: legal, help and accessibility.
 *
 * The split rule is in FOR_THE_TEAM.md — prose owned by a lawyer or a support
 * lead comes through here; anything with structured UI (a table, a grid, a
 * form, a directory) gets its own page file. That is why `/help` itself is not
 * one of these: it is a directory of topics, not an article.
 */
const props = defineProps<{
  slug: string
  /** Rendered under the body — help pages use it for the topic list. */
  footerHeading?: string
}>()

const { title, summary, html, sections, updated, isDraft } = await usePageContent(props.slug)

// Anchor rail for the longer policies. Below `md` it would cost more vertical
// space than it saves, so it only appears once there is a column to put it in.
const anchors = computed(() =>
  sections.value.map((section) => ({
    id: section.heading.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''),
    heading: section.heading,
  })),
)
</script>

<template>
  <article class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-8">
    <ContentDraftNotice v-if="isDraft" />

    <header class="flex w-full flex-col items-start gap-3">
      <h1 class="w-full text-display-section font-normal text-black">{{ title }}</h1>
      <p v-if="summary" class="w-full max-w-[720px] text-body text-graphite">{{ summary }}</p>
      <p v-if="updated" class="w-full text-caption text-muted">
        Last updated {{ formatPolicyDate(updated) }}
      </p>
    </header>

    <div class="flex w-full flex-col items-start gap-10 md:flex-row md:gap-12">
      <!-- Anchor rail. `md:sticky` needs the flex parent to be `items-start`,
           which it is — a stretched child has no room to stick within. -->
      <nav
        v-if="anchors.length > 2"
        class="hidden w-[200px] shrink-0 md:sticky md:top-8 md:block"
        aria-label="On this page"
      >
        <ul class="flex flex-col items-start gap-1 border-l border-line">
          <li v-for="anchor in anchors" :key="anchor.id" class="w-full">
            <a
              :href="`#${anchor.id}`"
              class="-my-1 flex min-h-[44px] w-full items-center border-l-2 border-transparent px-4 py-1 text-caption text-muted hover:border-graphite hover:text-graphite"
            >{{ anchor.heading }}</a>
          </li>
        </ul>
      </nav>

      <div class="min-w-0 flex-1">
        <!-- Approved CMS copy arrives as rich-text HTML. -->
        <div v-if="html" class="policy-body flex w-full max-w-[720px] flex-col items-start gap-6" v-html="html" />

        <!-- Draft copy is structured, so it renders as real nodes. -->
        <div v-else class="flex w-full max-w-[720px] flex-col items-start gap-10">
          <section
            v-for="(section, index) in sections"
            :id="anchors[index]?.id"
            :key="section.heading"
            class="flex w-full scroll-mt-24 flex-col items-start gap-3"
          >
            <h2 class="w-full text-display-sm font-normal text-black">{{ section.heading }}</h2>
            <p v-for="(paragraph, i) in section.body" :key="i" class="w-full text-body text-graphite">
              {{ paragraph }}
            </p>
          </section>

          <p v-if="!sections.length" class="w-full text-body text-muted">
            This page hasn’t been written yet.
          </p>
        </div>

        <div v-if="$slots.footer" class="mt-12 w-full max-w-[720px]">
          <h2 v-if="footerHeading" class="mb-4 w-full text-display-sm font-normal text-black">
            {{ footerHeading }}
          </h2>
          <slot name="footer" />
        </div>
      </div>
    </div>
  </article>
</template>

<style scoped>
/* Approved copy comes out of the CMS as rich text, so its elements are styled
   here rather than with utility classes — the same approach `BlogPost.vue`
   takes for article bodies. Policy prose sits on the fixed `body` scale rather
   than the editorial clamp() used there: a privacy policy read at 24px is
   harder to scan, not easier. */
.policy-body :deep(h2) {
  font-size: clamp(20px, 18.857px + 0.357vw, 24px); /* display-sm */
  line-height: 1.385;
  font-weight: 400;
  width: 100%;
  scroll-margin-top: 6rem;
}

.policy-body :deep(h3) {
  font-size: 16px;
  line-height: 24px;
  letter-spacing: 0.64px;
  font-weight: 400;
  width: 100%;
}

.policy-body :deep(p),
.policy-body :deep(li) {
  font-size: 16px;
  line-height: 24px;
  letter-spacing: 0.64px;
  font-weight: 300;
  width: 100%;
}

.policy-body :deep(a) {
  text-decoration: underline;
}

.policy-body :deep(a:hover) {
  text-decoration: none;
}

.policy-body :deep(ul),
.policy-body :deep(ol) {
  padding-left: 1.5rem;
  list-style: revert;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
</style>
