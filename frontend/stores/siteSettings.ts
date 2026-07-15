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
  }),
  actions: {
    setSettings(settings: Partial<ReturnType<typeof useSiteSettingsStore>['$state']>) {
      Object.assign(this, settings)
    },
  },
})
