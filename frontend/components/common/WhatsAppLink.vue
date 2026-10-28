<script setup lang="ts">
import { PhWhatsappLogo as WhatsappLogo } from '@phosphor-icons/vue'
import { withOrderCode, type WhatsAppSource } from '~/utils/whatsapp'

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
 *   outlined: `.btn-outline`: white, thin black outline, black on hover
 *   solid:    now the same `.btn-outline`; every button on the site is white
 *              at rest and black only when engaged, at the client's request
 *   gold:     `.btn-outline-on-dark`, for the black About band (it was gold)
 *   quiet:    an inline text link, for prose and helper text
 *
 * With `orderDraft` it is "Order on WhatsApp": on click the cart is saved
 * (`POST /whatsapp-orders`, which holds no stock) and its code is added to
 * the message, so the team opens the chat with the cart already there. The
 * WhatsApp tab is opened at once, inside the click, so popup blockers allow
 * it, and pointed at WhatsApp once the code is back. If saving fails or takes
 * too long, the customer still lands in WhatsApp with today's message: this
 * is the brand's main ordering channel and must never break on our account.
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
    /** The cart to carry into the chat, as checkout lines. */
    orderDraft?: {
      items: { slug: string, size: string | null, colour: string | null, quantity: number }[]
      currency: 'GHS' | 'USD'
    } | null
  }>(),
  { variant: 'outlined', full: false },
)

const codedMessage = ref<string | null>(null)
const { href } = useWhatsApp(() => codedMessage.value ?? props.message)
const { whatsappClick } = useAnalytics()
const api = useApi()

/** How long the customer waits for the code before WhatsApp opens without it. */
const DRAFT_TIMEOUT_MS = 2500

async function onClick(event: MouseEvent) {
  whatsappClick({ source: props.source })

  const items = props.orderDraft?.items ?? []
  // A modified click (new tab, new window) keeps the browser's own behaviour.
  if (!items.length || !href.value || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return

  event.preventDefault()
  const plain = href.value
  const tab = window.open('', '_blank')
  let target = plain

  try {
    const saved = await Promise.race([
      api<{ data: { reference: string } }>('/whatsapp-orders', {
        method: 'POST',
        body: { items, currency: props.orderDraft!.currency },
      }),
      new Promise<never>((_, reject) => setTimeout(() => reject(new Error('timeout')), DRAFT_TIMEOUT_MS)),
    ])
    codedMessage.value = withOrderCode(props.message ?? '', saved.data.reference)
    await nextTick()
    target = href.value ?? plain
  } catch {
    // Open WhatsApp anyway, with the message as it always was.
  } finally {
    codedMessage.value = null
  }

  if (tab) {
    tab.opener = null
    tab.location.href = target
  } else {
    window.location.href = target
  }
}

const showIcon = computed(() => props.icon ?? props.variant !== 'quiet')

const variantClass = computed(
  () =>
    ({
      // `text-center` and `leading-tight`: in a narrow product card the label
      // wraps to two lines, which should sit centred, not ragged-left.
      outlined:
        'btn-outline min-h-[44px] justify-center px-3 py-2 text-center text-label uppercase leading-tight',
      solid:
        'btn-outline min-h-[44px] justify-center px-3 py-2 text-center text-label uppercase leading-tight',
      gold:
        'btn-outline-on-dark min-h-[44px] justify-center px-6 py-2 text-center text-label font-normal uppercase leading-tight',
      // `-my-2` keeps an inline link from adding height to the sentence it sits
      // in while its hit area still clears 44px.
      // Graphite, like the site's other text links. It was gold, from before
      // the restyle; the footer had been overriding it back to graphite.
      quiet:
        '-my-2 min-h-[44px] py-2 text-caption text-graphite underline decoration-graphite/40 underline-offset-2 hover:text-ink hover:decoration-ink',
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
    @click="onClick"
  >
    <WhatsappLogo v-if="showIcon" :size="variant === 'quiet' ? 14 : 18" weight="fill" aria-hidden="true" />
    <slot />
  </a>
</template>
