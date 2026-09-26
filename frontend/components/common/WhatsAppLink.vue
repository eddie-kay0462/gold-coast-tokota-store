<script setup lang="ts">
import { PhWhatsappLogo as WhatsappLogo } from '@phosphor-icons/vue'
import type { WhatsAppSource } from '~/utils/whatsapp'

/**
 * Every WhatsApp call-to-action on the storefront, except the floating button.
 *
 * Before this there were five labels and three different button treatments for
 * the same action, and no single place to hang the analytics on. This owns the
 * things that must not vary: the `v-if` guard that hides the CTA rather than
 * linking somewhere invalid (README Feature 6's edge case), the 44px tap
 * target, `rel="noopener noreferrer"`, the glyph, and the click event.
 *
 * The variants mirror `CommonBrandButton`'s vocabulary rather than inventing a
 * parallel one:
 *   outlined — the shop/cart treatment: hairline box that inverts on hover
 *   solid    — ink fill, for a primary action
 *   gold     — the approved mockup's gold-on-dark About CTA
 *   quiet    — an inline text link, for prose and helper text
 */
const props = withDefaults(
  defineProps<{
    /** Prefilled text. Build it with `whatsappMessage.*`, never inline. */
    message?: string
    /** Which affordance this is, for `whatsapp_click`. */
    source: WhatsAppSource
    variant?: 'outlined' | 'solid' | 'gold' | 'quiet'
    /** Stretch to the container instead of sitting at its content width. */
    full?: boolean
    /** `quiet` has no glyph by default; the others do. */
    icon?: boolean
  }>(),
  { variant: 'outlined', full: false },
)

const { href } = useWhatsApp(() => props.message)
const { whatsappClick } = useAnalytics()

const showIcon = computed(() => props.icon ?? props.variant !== 'quiet')

const variantClass = computed(
  () =>
    ({
      outlined:
        'min-h-[44px] justify-center border border-graphite bg-white px-4 text-label uppercase text-graphite transition-colors hover:bg-graphite hover:text-white',
      solid:
        'min-h-[44px] justify-center border border-ink bg-ink px-4 text-label uppercase text-white transition-opacity hover:opacity-80',
      gold:
        'min-h-[44px] justify-center bg-gold px-6 text-label font-normal uppercase text-chrome transition-opacity hover:opacity-90',
      // `-my-2` keeps an inline link from adding height to the sentence it sits
      // in while its hit area still clears 44px.
      quiet:
        '-my-2 min-h-[44px] py-2 text-caption text-gold-deep underline decoration-gold-deep/40 underline-offset-2 hover:text-graphite',
    })[props.variant],
)
</script>

<template>
  <a
    v-if="href"
    :href="href"
    target="_blank"
    rel="noopener noreferrer"
    class="inline-flex items-center gap-2"
    :class="[variantClass, full ? 'flex w-full' : '']"
    @click="whatsappClick({ source })"
  >
    <WhatsappLogo v-if="showIcon" :size="variant === 'quiet' ? 14 : 18" weight="fill" aria-hidden="true" />
    <slot />
  </a>
</template>
