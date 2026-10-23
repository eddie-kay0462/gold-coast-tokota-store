<script setup lang="ts">
import type { MediaAsset } from '~/types'

/**
 * One image on a designed page: the default, or a media library image chosen
 * instead, plus its alt text. "Use default" goes back to the designed image.
 */
const props = defineProps<{
  /** The designed image's storefront path. */
  defaultSrc: string
  /** Shown while a chosen image's URL isn't known yet (before saving). */
  currentUrl?: string
  disabled?: boolean
}>()

const imageId = defineModel<number | null>('imageId', { required: true })
const alt = defineModel<string>('alt', { required: true })

const config = useRuntimeConfig()
const picking = ref(false)
const picked = ref<{ url: string, filename: string } | null>(null)

const preview = computed(() => {
  if (imageId.value && picked.value) return picked.value.url
  if (imageId.value && props.currentUrl) return props.currentUrl
  return `${config.public.storefrontUrl}${props.defaultSrc}`
})

function onPick(asset: MediaAsset) {
  imageId.value = asset.id
  picked.value = { url: asset.url, filename: asset.filename }
  if (!alt.value.trim() && asset.altText) alt.value = asset.altText
}

function useDefault() {
  imageId.value = null
  picked.value = null
}
</script>

<template>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
    <img :src="preview" :alt="alt" class="aspect-[4/3] w-full shrink-0 rounded-md bg-bg-sunken object-cover sm:w-48">
    <div class="flex min-w-0 flex-1 flex-col gap-2">
      <p class="text-meta text-fg-muted">
        {{ imageId ? (picked?.filename ? `Chosen: ${picked.filename}` : 'A media library image') : 'The designed image' }}
      </p>
      <div v-if="!disabled" class="flex flex-wrap gap-2">
        <UiButton variant="secondary" size="sm" @click="picking = true">{{ imageId ? 'Change image' : 'Choose image' }}</UiButton>
        <UiButton v-if="imageId" variant="ghost" size="sm" @click="useDefault">Use designed image</UiButton>
      </div>
      <UiField v-model="alt" label="Alt text" hint="Describes the picture for people who can't see it." :disabled="disabled" />
    </div>
    <ContentMediaPicker v-model:open="picking" @select="onPick" />
  </div>
</template>
