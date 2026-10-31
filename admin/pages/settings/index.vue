<script setup lang="ts">
import type { SiteSettings } from '~/types'

/**
 * General site settings: the owner-editable values README Feature 9 requires
 * be changeable without a developer: contact details, hero copy, socials.
 */
useHead({ title: 'Settings' })

const { useAdminItem, adminFetch } = useAdminApi()
const { formatRelative } = useFormatters()
const { can } = useAuth()
const toast = useToast()
const { item: settings, refresh } = useAdminItem<SiteSettings>('site-settings', '/admin/site-settings')

const form = reactive({
  contactEmail: '', contactPhone: '', address: '',
  instagramUrl: '', heroHeadline: '', heroSubheadline: '',
  announcements: [] as string[],
})
watchEffect(() => {
  if (!settings.value) return
  Object.assign(form, {
    contactEmail: settings.value.contactEmail ?? '',
    contactPhone: settings.value.contactPhone ?? '',
    address: settings.value.address ?? '',
    instagramUrl: settings.value.instagramUrl ?? '',
    heroHeadline: settings.value.heroHeadline ?? '',
    heroSubheadline: settings.value.heroSubheadline ?? '',
    announcements: [...(settings.value.announcements ?? [])],
  })
})

type Section = 'contact' | 'hero' | 'announcements'
const saving = ref<Section | null>(null)

/** Partial update: each section saves only its own fields. */
async function save(section: Section) {
  const body = {
    contact: {
      contact_email: form.contactEmail.trim() || null,
      contact_phone: form.contactPhone.trim() || null,
      address: form.address.trim() || null,
      instagram_url: form.instagramUrl.trim() || null,
    },
    hero: {
      hero_headline: form.heroHeadline.trim() || null,
      hero_subheadline: form.heroSubheadline.trim() || null,
    },
    announcements: { announcements: form.announcements.map((a) => a.trim()).filter(Boolean) },
  }[section]
  saving.value = section
  try {
    await adminFetch('/admin/site-settings', { method: 'PUT', body })
    toast.success(
      { contact: 'Contact details saved', hero: 'Hero text saved', announcements: 'Announcements saved' }[section],
      'Live on the storefront now.',
    )
    await refresh()
  } catch (err) {
    const message = failureMessage(err)
    if (message) toast.error('Not saved', message)
  } finally {
    saving.value = null
  }
}
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
            <UiField v-model="form.address" label="Address" class="md:col-span-2" />
            <UiField
              v-model="form.instagramUrl" label="Instagram" type="url"
              hint="Opens in a new tab from the header and footer."
              class="md:col-span-2"
            />
          </div>
          <template #footer>
            <UiPermissionGate capability="settings.write" quiet>
              <UiButton size="sm" :loading="saving === 'contact'" @click="save('contact')">Save</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection title="Home hero" description="The two lines over the storefront hero image.">
          <div class="flex flex-col gap-4">
            <UiField
              v-model="form.heroHeadline" label="Headline" placeholder="Your Step into Heritage"
              hint="Keep it short. It shows at display size on phones as well as desktops. Blank uses the one shown."
            />
            <UiField
              v-model="form.heroSubheadline" label="Second line" maxlength="255"
              placeholder="Get your pair of authentic locally-made Ghanaian footwear."
              hint="Under the headline, a size smaller. Blank uses the line shown."
            />
          </div>
          <template #footer>
            <UiPermissionGate capability="settings.write" quiet>
              <UiButton size="sm" :loading="saving === 'hero'" @click="save('hero')">Save</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection
          title="Announcement bar"
          description="The short lines that take turns in the bar above the storefront header. Up to five, 120 characters each. With none, the bar shows its built-in lines."
        >
          <SettingsAnnouncementPreview :messages="form.announcements" :hours="settings?.businessHours" class="mb-4" />
          <ContentListEditor
            v-model="form.announcements" noun="line" :blank="() => ''" :max="5"
            :disabled="!can('settings.write')"
          >
            <template #default="{ index }">
              <UiField
                v-model="form.announcements[index]" :aria-label="`Announcement ${index + 1}`"
                placeholder="e.g. Handcrafted in Ghana" :disabled="!can('settings.write')"
              />
            </template>
          </ContentListEditor>
          <template #footer>
            <UiPermissionGate capability="settings.write" quiet>
              <UiButton size="sm" :loading="saving === 'announcements'" @click="save('announcements')">Save</UiButton>
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
