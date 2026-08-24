import type { MaybeRefOrGetter } from 'vue'
import { useSiteSettingsStore } from '~/stores/siteSettings'

// Shared wa.me link builder — WhatsAppButton and inline product/booking CTAs
// all derive from the same admin-editable SiteSetting number, never hardcoded.
export function useWhatsApp(customMessage?: MaybeRefOrGetter<string | undefined>) {
  const siteSettings = useSiteSettingsStore()

  const href = computed(() => {
    if (!siteSettings.whatsappNumber) return null
    const message = encodeURIComponent(toValue(customMessage) ?? siteSettings.whatsappDefaultMessage ?? '')
    return `https://wa.me/${siteSettings.whatsappNumber}?text=${message}`
  })

  return { href }
}
