<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'

const siteSettings = useSiteSettingsStore()

// The Instagram destination is admin-editable (SiteSetting), the same source
// the footer's Connect column uses — never hardcoded per component.
const instagramUrl = computed(() => siteSettings.instagramUrl || '/')
const isExternal = computed(() => !!siteSettings.instagramUrl)
</script>

<template>
  <!-- Figma 10:1038 -->
  <section class="flex w-full flex-col items-start gap-8 bg-ink px-5 py-16 lg:px-[60px] lg:py-[90px]">
    <h2 class="w-full font-normal text-display-md text-white lg:text-display-heading">
      Follow us on social for more
    </h2>

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
  </section>
</template>
