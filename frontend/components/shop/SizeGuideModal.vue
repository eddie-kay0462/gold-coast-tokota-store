<script setup lang="ts">
import { whatsappMessage } from '~/utils/whatsapp'
import { SIZE_GUIDE_BETWEEN_SIZES, SIZE_GUIDE_STEPS } from '~/utils/sizeGuide'

/**
 * The size guide, opened over the product page so the shopper never leaves
 * the pair they're choosing a size for. Same content as `/size-guide`, both
 * read from `utils/sizeGuide.ts`. The chart comes first here because it's what
 * someone mid-purchase opens this for.
 */
defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()

const { href: whatsappHref } = useWhatsApp(() => whatsappMessage.sizing())
const { whatsappClick } = useAnalytics()
</script>

<template>
  <CommonModal :open="open" title="Size Guide" size="lg" @close="emit('close')">
    <div class="flex flex-col gap-6">
      <section class="flex flex-col gap-2.5">
        <h3 class="text-label font-normal text-black">Size chart</h3>
        <ShopSizeChart dense />
        <p class="text-caption text-muted">{{ SIZE_GUIDE_BETWEEN_SIZES }}</p>
      </section>

      <section class="flex flex-col gap-2.5">
        <h3 class="text-label font-normal text-black">How to measure</h3>
        <ol class="flex list-decimal flex-col gap-2 pl-5 text-body font-light text-graphite">
          <li v-for="step in SIZE_GUIDE_STEPS" :key="step">{{ step }}</li>
        </ol>
      </section>
    </div>

    <!-- Laid out as a line of text plus separate links, not one sentence with
         links inline. The inline version depended on whitespace between a
         `<template v-if>` and the link after it, which Vue drops, and it
         rendered as "See thefull size guide." -->
    <template #footer>
      <div class="flex flex-col gap-2 bg-ink px-6 py-3.5 text-white sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-8">
        <p class="text-label font-light">
          Still unsure? {{ whatsappHref ? 'We’ll help you pick a size.' : 'The full guide has more detail.' }}
        </p>
        <div class="-my-2.5 flex flex-wrap items-center gap-x-6">
          <a
            v-if="whatsappHref"
            :href="whatsappHref"
            target="_blank"
            rel="noopener noreferrer"
            class="flex min-h-[44px] items-center text-tag uppercase tracking-[1px] underline underline-offset-4 hover:no-underline"
            @click="whatsappClick({ source: 'size-guide' })"
          >
            Ask on WhatsApp
          </a>
          <NuxtLink
            to="/size-guide"
            class="flex min-h-[44px] items-center text-tag uppercase tracking-[1px] underline underline-offset-4 hover:no-underline"
          >
            Full size guide
          </NuxtLink>
        </div>
      </div>
    </template>
  </CommonModal>
</template>
