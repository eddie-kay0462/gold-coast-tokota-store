<script setup lang="ts">
import { PhImage, PhPencilSimple, PhTrash, PhUploadSimple } from '@phosphor-icons/vue'
import type { MediaAsset } from '~/types'
import type { LightboxImage } from '~/components/ui/Lightbox.vue'

useHead({ title: 'Media' })

const { useAdminList, adminFetch } = useAdminApi()
const { formatRelative, formatBytes } = useFormatters()
const { can } = useAuth()
const { items: assets, pending, refresh } = useAdminList<MediaAsset>('admin-media', '/admin/media')

const search = ref('')
const visible = computed(() => {
  const q = search.value.trim().toLowerCase()
  return q ? assets.value.filter((a) => a.filename.toLowerCase().includes(q)) : assets.value
})

/** Flag anything without alt text; it is the accessibility gap that scales. */
const missingAlt = computed(() => assets.value.filter((a) => !a.altText.trim()).length)

// The viewer steps through what the search is showing.
const viewerImages = computed<LightboxImage[]>(() => visible.value.map((a) => ({
  src: a.url,
  alt: a.altText || a.filename,
  caption: a.filename,
  meta: [a.width && a.height ? `${a.width}×${a.height}` : null, formatBytes(a.sizeBytes), a.altText || 'No alt text']
    .filter(Boolean).join(' · '),
})))
const viewing = ref<number | null>(null)

const toast = useToast()
const uploading = ref(false)

const sending = ref(false)

/** `POST /admin/media`, one file at a time so a bad one doesn't sink the rest. */
async function onFiles(files: File[]) {
  sending.value = true
  let sent = 0
  const refused: string[] = []
  for (const file of files) {
    const body = new FormData()
    body.append('file', file)
    try {
      await adminFetch('/admin/media', { method: 'POST', body })
      sent++
    } catch (err) {
      const message = failureMessage(err, 'It didn’t upload.')
      if (message === null) break
      refused.push(`${file.name}: ${message}`)
    }
  }
  sending.value = false
  if (sent) {
    uploading.value = false
    toast.success(`${sent} image${sent === 1 ? '' : 's'} uploaded`, 'Add alt text to each one so screen readers can describe it.')
    await refresh()
  }
  if (refused.length) toast.error(`${refused.length} not uploaded`, refused.join(' '))
}

// --- Alt text and delete --------------------------------------------------
const editingAlt = ref<number | null>(null)
const altDraft = ref('')
const savingAlt = ref(false)
function editAlt(a: MediaAsset) {
  editingAlt.value = a.id
  altDraft.value = a.altText
}
async function saveAlt(a: MediaAsset) {
  savingAlt.value = true
  try {
    await adminFetch(`/admin/media/${a.id}`, { method: 'PATCH', body: { alt_text: altDraft.value.trim() || null } })
    editingAlt.value = null
    await refresh()
  } catch (err) {
    const message = failureMessage(err)
    if (message) toast.error('Not saved', message)
  } finally {
    savingAlt.value = false
  }
}

const deleting = ref<MediaAsset | null>(null)
const deleteOpen = ref(false)
const busyDeleting = ref(false)
function askDelete(a: MediaAsset) {
  deleting.value = a
  deleteOpen.value = true
}
async function confirmDelete() {
  if (!deleting.value) return
  busyDeleting.value = true
  try {
    await adminFetch(`/admin/media/${deleting.value.id}`, { method: 'DELETE' })
    toast.success('Image deleted', deleting.value.filename)
    deleteOpen.value = false
    await refresh()
  } catch (err) {
    const message = failureMessage(err, 'Not deleted. Try again in a moment.')
    if (message) toast.error('Not deleted', message)
  } finally {
    busyDeleting.value = false
  }
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
      <UiFileDrop accept="image/jpeg,image/png,image/webp,image/avif" :max-size-mb="8" @files="onFiles" />
      <p v-if="sending" class="text-meta text-fg-muted" role="status">Uploading…</p>
    </UiPermissionGate>

    <UiToolbar v-model:search="search" placeholder="Search by filename…" />

    <div v-if="pending" class="grid gap-4 sm:grid-cols-3 xl:grid-cols-4">
      <div v-for="i in 8" :key="i" class="aspect-[4/3] animate-pulse rounded-lg bg-bg-sunken" />
    </div>

    <div v-else-if="!visible.length" class="card">
      <UiEmptyState title="No images match" :icon="PhImage" />
    </div>

    <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
      <li v-for="(a, i) in visible" :key="a.id" class="card overflow-hidden">
        <button
          type="button"
          class="flex aspect-[4/3] w-full items-center justify-center overflow-hidden bg-bg-sunken text-fg-faint"
          :aria-label="`View ${a.filename}`"
          @click="viewing = i"
        >
          <img v-if="a.url" :src="a.url" :alt="a.altText || a.filename" loading="lazy" class="size-full object-contain">
          <PhImage v-else :size="28" />
        </button>
        <div class="p-3">
          <p class="truncate text-ui text-fg-strong">{{ a.filename }}</p>
          <p class="mt-0.5 text-meta text-fg-faint">
            {{ a.width }}×{{ a.height }} · {{ formatBytes(a.sizeBytes) }}
          </p>
          <form v-if="editingAlt === a.id" class="mt-1.5 flex gap-1.5" @submit.prevent="saveAlt(a)">
            <input
              v-model="altDraft" class="field min-w-0 flex-1 text-meta" maxlength="255"
              :aria-label="`Alt text for ${a.filename}`" placeholder="Describe the image"
            >
            <UiButton size="sm" type="submit" :loading="savingAlt">Save</UiButton>
          </form>
          <p v-else class="mt-1.5 flex items-center gap-1.5 text-meta" :class="a.altText ? 'text-fg-muted' : 'text-warning'">
            <span class="min-w-0 flex-1 truncate">{{ a.altText || 'No alt text' }}</span>
            <button
              v-if="can('media.upload')" type="button" class="toolbar-btn !size-7 shrink-0"
              :aria-label="`Edit alt text for ${a.filename}`" @click="editAlt(a)"
            >
              <PhPencilSimple :size="14" />
            </button>
            <button
              v-if="can('media.delete')" type="button" class="toolbar-btn !size-7 shrink-0 hover:text-danger"
              :aria-label="`Delete ${a.filename}`" @click="askDelete(a)"
            >
              <PhTrash :size="14" />
            </button>
          </p>
          <p class="mt-1 truncate text-meta text-fg-faint">
            {{ a.uploadedByName ?? 'Catalogue import' }} · {{ formatRelative(a.uploadedAt) }}
          </p>
        </div>
      </li>
    </ul>

    <UiLightbox v-model:index="viewing" :images="viewerImages" />

    <UiModal v-model:open="deleteOpen" size="sm" title="Delete this image?" :description="deleting?.filename">
      <p class="text-ui text-fg-muted">
        Anywhere it’s used (a post cover, a menu tile, a page) shows a broken image until
        another is chosen there.
      </p>
      <template #footer>
        <UiButton variant="secondary" size="sm" @click="deleteOpen = false">Keep it</UiButton>
        <UiButton variant="danger" size="sm" :loading="busyDeleting" @click="confirmDelete">Delete</UiButton>
      </template>
    </UiModal>
  </div>
</template>
