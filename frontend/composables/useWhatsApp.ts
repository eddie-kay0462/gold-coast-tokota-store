import type { MaybeRefOrGetter } from 'vue'
import { useSiteSettingsStore } from '~/stores/siteSettings'

/**
 * The one `wa.me` link builder. Every WhatsApp affordance on the storefront
 * derives from this, so the number lives in exactly one place and an admin
 * change takes effect site-wide with no deploy (README Feature 6).
 *
 * Message text comes from `~/utils/whatsapp`; this only builds the URL.
 */

/** `wa.me` accepts digits only — no `+`, no spaces, no punctuation. */
const MIN_DIGITS = 8

/**
 * Normalise whatever the admin typed into something `wa.me` accepts.
 *
 * The settings field asks for "international format, including the country
 * code", and the admin app's own fixture stores `+233257534297` — so the stored
 * value routinely contains characters the URL cannot carry. This used to be
 * interpolated raw, which produced a link with a literal `+` and spaces in it
 * *that still rendered as a working button*. That is the exact edge case
 * Feature 6 calls out: a misconfigured number must fail visibly, not silently.
 *
 * Anything with too few digits left to be a phone number resolves to null, and
 * every caller then renders nothing at all.
 */
function normaliseNumber(raw: string | undefined | null): string | null {
  const digits = (raw ?? '').replace(/\D/g, '')
  return digits.length >= MIN_DIGITS ? digits : null
}

export function useWhatsApp(customMessage?: MaybeRefOrGetter<string | undefined>) {
  const siteSettings = useSiteSettingsStore()
  const config = useRuntimeConfig()

  /**
   * Site settings win; the env var is a fallback for when `GET /site-settings`
   * is unreachable. Without it a single failed request removes the only working
   * ordering channel from every page at once — and payment is still inert, so
   * that is the whole shop closing rather than one button vanishing.
   */
  const number = computed(
    () =>
      normaliseNumber(siteSettings.whatsappNumber)
      ?? normaliseNumber(config.public.whatsappNumber as string | undefined),
  )

  const href = computed(() => {
    if (!number.value) return null

    // An empty custom message falls through to the configured default rather
    // than suppressing it — `??` alone would let `''` win.
    const custom = toValue(customMessage)
    const message = (custom || siteSettings.whatsappDefaultMessage || '').trim()

    // No message means no `?text=` at all, rather than a bare trailing one.
    return message
      ? `https://wa.me/${number.value}?text=${encodeURIComponent(message)}`
      : `https://wa.me/${number.value}`
  })

  return { href }
}
