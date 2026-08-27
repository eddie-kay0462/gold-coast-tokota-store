import { defineStore } from 'pinia'

// Backs WhatsAppButton and footer contact info — sourced from the admin-editable
// SiteSetting API resource so these never need hardcoding in multiple components.
export const useSiteSettingsStore = defineStore('siteSettings', {
  state: () => ({
    whatsappNumber: '',
    whatsappDefaultMessage: '',
    contactEmail: '',
    contactPhone: '',
    instagramUrl: '',
    heroHeadline: '',
    heroImage: '',
    diyTurnaroundEstimate: '',
    /**
     * Rotating announcement-bar messages, in display order. Admin-editable
     * because the copy makes commercial claims (delivery, payment methods)
     * that must not need a deploy to correct.
     */
    announcements: [] as string[],
  }),
  actions: {
    setSettings(settings: Partial<ReturnType<typeof useSiteSettingsStore>['$state']>) {
      Object.assign(this, settings)
    },
  },
})
