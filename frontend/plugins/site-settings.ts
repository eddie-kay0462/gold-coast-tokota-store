import { useSiteSettingsStore } from '~/stores/siteSettings'

// Runs on every SSR request and on client-only navigation, so the
// WhatsAppButton and footer contact info are populated before first paint
// on every route — including SPA-only ones that skip the SSR pass.
export default defineNuxtPlugin(async () => {
  const config = useRuntimeConfig()
  const siteSettings = useSiteSettingsStore()

  const { data } = await useAsyncData('site-settings', () =>
    $fetch<{ data: Record<string, string> }>(`${config.public.apiBase}/site-settings`),
  )

  if (data.value?.data) {
    siteSettings.setSettings({
      whatsappNumber: data.value.data.whatsapp_number,
      whatsappDefaultMessage: data.value.data.whatsapp_default_message,
      contactEmail: data.value.data.contact_email,
      contactPhone: data.value.data.contact_phone,
      instagramUrl: data.value.data.instagram_url,
      heroHeadline: data.value.data.hero_headline,
      heroImage: data.value.data.hero_image,
      diyTurnaroundEstimate: data.value.data.diy_turnaround_estimate,
    })
  }
})
