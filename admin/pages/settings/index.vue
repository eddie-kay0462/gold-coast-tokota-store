<script setup lang="ts">
import type { SiteSettings } from '~/types'

/**
 * General site settings — the owner-editable values README Feature 9 requires
 * be changeable without a developer: contact details, hero copy, socials.
 */
useHead({ title: 'Settings' })

const { useAdminItem } = useAdminApi()
const { formatRelative } = useFormatters()
const { item: settings } = useAdminItem<SiteSettings>('site-settings', '/admin/site-settings')

const form = reactive({
  contactEmail: '', contactPhone: '', addressLine: '',
  instagramUrl: '', heroHeadline: '',
})
watchEffect(() => {
  if (!settings.value) return
  Object.assign(form, {
    contactEmail: settings.value.contactEmail,
    contactPhone: settings.value.contactPhone,
    addressLine: settings.value.addressLine,
    instagramUrl: settings.value.instagramUrl,
    heroHeadline: settings.value.heroHeadline,
  })
})
</script>

<template>
  <SettingsShell
    title="Settings"
    description="Site-wide values the storefront reads. Changes take effect immediately, with no deploy."
  >
    <UiPermissionGate capability="settings.view">
      <div class="admin-stack">
        <SettingsSection
          title="Contact"
          description="Shown in the storefront footer and on the contact page."
        >
          <div class="grid gap-4 md:grid-cols-2">
            <UiField v-model="form.contactEmail" label="Contact email" type="email" />
            <UiField v-model="form.contactPhone" label="Contact phone" />
            <UiField v-model="form.addressLine" label="Address" class="md:col-span-2" />
            <UiField
              v-model="form.instagramUrl" label="Instagram" type="url"
              hint="Opens in a new tab from the header and footer."
              class="md:col-span-2"
            />
          </div>
          <template #footer>
            <UiPermissionGate capability="settings.write" quiet>
              <UiButton size="sm">Save</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection title="Home hero" description="The headline over the storefront hero.">
          <UiField
            v-model="form.heroHeadline" label="Headline"
            hint="Kept short — it renders at display size on a phone as well as a desktop."
          />
          <template #footer>
            <UiPermissionGate capability="settings.write" quiet>
              <UiButton size="sm">Save</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <p v-if="settings" class="text-meta text-fg-faint">
          Last updated {{ formatRelative(settings.updatedAt) }}.
        </p>
      </div>
    </UiPermissionGate>
  </SettingsShell>
</template>
