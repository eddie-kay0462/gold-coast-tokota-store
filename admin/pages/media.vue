<script setup lang="ts">
import { PhImage, PhUploadSimple } from '@phosphor-icons/vue'
import type { MediaAsset } from '~/types'

useHead({ title: 'Media' })

const { useAdminList } = useAdminApi()
const { formatRelative, formatBytes } = useFormatters()
const { items: assets, pending } = useAdminList<MediaAsset>('admin-media', '/admin/media')

const search = ref('')
const visible = computed(() => {
  const q = search.value.trim().toLowerCase()
  return q ? assets.value.filter((a) => a.filename.toLowerCase().includes(q)) : assets.value
})

/** Flag anything without alt text — it is the accessibility gap that scales. */
const missingAlt = computed(() => assets.value.filter((a) => !a.altText.trim()).length)

const toast = useToast()
const uploading = ref(false)

function onFiles(files: File[]) {
  uploading.value = false
  toast.info(
    `${files.length} file${files.length === 1 ? '' : 's'} selected`,
    'Upload needs the media endpoint, which is still to be built. Nothing was sent.',
  )
}
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Media" description="Images used across products, posts and pages.">
      <template #actions>
        <UiPermissionGate capability="media.upload" quiet>
          <UiButton size="sm" @click="uploading = !uploading">
            <PhUploadSimple :size="16" />Upload
          </UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <p
      v-if="missingAlt"
      class="rounded-lg border border-warning/30 bg-warning-soft px-3.5 py-2.5 text-ui text-warning"
    >
      {{ missingAlt }} image{{ missingAlt === 1 ? ' has' : 's have' }} no alt text.
    </p>

    <UiPermissionGate v-if="uploading" capability="media.upload" quiet>
      <UiFileDrop @files="onFiles" />
    </UiPermissionGate>

    <UiToolbar v-model:search="search" placeholder="Search by filename…" />

    <div v-if="pending" class="grid gap-4 sm:grid-cols-3 xl:grid-cols-4">
      <div v-for="i in 8" :key="i" class="aspect-[4/3] animate-pulse rounded-lg bg-bg-sunken" />
    </div>

    <div v-else-if="!visible.length" class="card">
      <UiEmptyState title="No images match" :icon="PhImage" />
    </div>

    <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
      <li v-for="a in visible" :key="a.id" class="card overflow-hidden">
        <div class="flex aspect-[4/3] items-center justify-center bg-bg-sunken text-fg-faint">
          <PhImage :size="28" />
        </div>
        <div class="p-3">
          <p class="truncate text-ui text-fg-strong">{{ a.filename }}</p>
          <p class="mt-0.5 text-meta text-fg-faint">
            {{ a.width }}×{{ a.height }} · {{ formatBytes(a.sizeBytes) }}
          </p>
          <p class="mt-1.5 truncate text-meta" :class="a.altText ? 'text-fg-muted' : 'text-warning'">
            {{ a.altText || 'No alt text' }}
          </p>
          <p class="mt-1 truncate text-meta text-fg-faint">
            {{ a.uploadedByName }} · {{ formatRelative(a.uploadedAt) }}
          </p>
        </div>
      </li>
    </ul>
  </div>
</template>
