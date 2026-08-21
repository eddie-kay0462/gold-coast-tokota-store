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
  <!-- The black band stays full-bleed; only its contents are capped, so the
       heading and button don't drift to the far left of a 2560px screen. -->
  <section class="page-gutter section-y flex w-full flex-col items-start bg-ink">
    <div class="mx-auto flex w-full max-w-[1320px] flex-col items-start gap-8">
      <h2 class="w-full font-normal text-display-heading text-white">
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
    </div>
  </section>
</template>
