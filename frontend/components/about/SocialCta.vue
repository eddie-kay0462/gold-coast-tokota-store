<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'
import { whatsappMessage } from '~/utils/whatsapp'

const siteSettings = useSiteSettingsStore()

// The Instagram destination is admin-editable (SiteSetting), the same source
// the footer's Connect column uses — never hardcoded per component.
const instagramUrl = computed(() => siteSettings.instagramUrl || '/')
const supportHours = computed(() => siteSettings.businessHours || 'Mon–Sat, 9am–5pm GMT')
const isExternal = computed(() => !!siteSettings.instagramUrl)
</script>

<template>
  <!-- Figma 10:1038 -->
  <!-- The black band stays full-bleed; only its contents are capped, so the
       heading and button don't drift to the far left of a 2560px screen. -->
  <section class="page-gutter section-y flex w-full flex-col items-start bg-ink">
    <div class="mx-auto flex w-full max-w-[1320px] flex-col items-start gap-8">
      <div class="flex w-full flex-col items-start gap-3">
        <h2 class="w-full font-normal text-display-heading text-white">
          Let us build your pair
        </h2>
        <p class="w-full max-w-[520px] text-body font-light text-white/70">
          Message us on WhatsApp. We reply {{ supportHours }}.
        </p>
      </div>

      <!-- The approved mockup's About band: a gold WhatsApp CTA on black, with
           Instagram beside it. Instagram was the only thing here before, which
           left the page's closing call-to-action pointing at a feed rather than
           at the channel that can actually take an order. -->
      <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
        <CommonWhatsAppLink source="contact" variant="gold" :message="whatsappMessage.general()">
          Chat on WhatsApp
        </CommonWhatsAppLink>

        <CommonBrandButton
          :to="instagramUrl"
          variant="white"
          shape="soft"
          :external="isExternal"
          :target="isExternal ? '_blank' : undefined"
          :rel="isExternal ? 'noopener noreferrer' : undefined"
        >
          @GoldCoastTokota Instagram
        </CommonBrandButton>
      </div>
    </div>
  </section>
</template>
