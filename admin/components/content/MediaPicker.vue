<script setup lang="ts">
import { PhImage, PhMagnifyingGlass } from '@phosphor-icons/vue'
import type { MediaAsset } from '~/types'

/**
 * Choose one image from the media library. Loads every page of
 * `GET /admin/media` (50 a page) when opened, and searches file names and
 * alt text. Uploading stays on the Media screen.
 */
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ select: [MediaAsset] }>()

const { adminFetch } = useAdminApi()
const assets = ref<MediaAsset[]>([])
const loading = ref(false)
const failed = ref(false)
const search = ref('')

async function load() {
  loading.value = true
  failed.value = false
  try {
    const all: MediaAsset[] = []
    for (let page = 1; page <= 20; page++) {
      const res = await adminFetch<MediaAsset[]>('/admin/media', { query: { page } })
      all.push(...res.data)
      const last = Number(res.meta?.lastPage ?? 1)
      if (page >= last) break
    }
    assets.value = all
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

watch(open, (isOpen) => {
  if (isOpen && !assets.value.length) load()
})

const visible = computed(() => {
  const q = search.value.trim().toLowerCase()
  return q
    ? assets.value.filter((a) => a.filename.toLowerCase().includes(q) || a.altText.toLowerCase().includes(q))
    : assets.value
})

function choose(asset: MediaAsset) {
  emit('select', asset)
  open.value = false
}
</script>

<template>
  <UiModal v-model:open="open" title="Choose an image" description="From the media library. Upload new photos on the Media screen." size="lg">
    <div class="relative mb-3">
      <PhMagnifyingGlass :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-fg-faint" />
      <input v-model="search" type="search" placeholder="Search by file name or alt text" aria-label="Search images" class="field min-h-[40px] pl-9">
    </div>

    <p v-if="loading" class="py-10 text-center text-ui text-fg-muted">Loading images…</p>
    <p v-else-if="failed" class="py-10 text-center text-ui text-danger">The media library didn’t load. Close this and try again.</p>
    <p v-else-if="!visible.length" class="py-10 text-center text-ui text-fg-muted">No images match.</p>

    <ul v-else class="grid grid-cols-3 gap-2 sm:grid-cols-4">
      <li v-for="asset in visible" :key="asset.id">
        <button
          type="button"
          class="group flex w-full flex-col gap-1 rounded-lg p-1 text-left hover:bg-bg-sunken focus-visible:bg-bg-sunken"
          :aria-label="`Choose ${asset.altText || asset.filename}`"
          @click="choose(asset)"
        >
          <span class="flex aspect-[4/3] w-full items-center justify-center overflow-hidden rounded-md bg-bg-sunken text-fg-faint">
            <img v-if="asset.url" :src="asset.url" :alt="asset.altText" class="size-full object-cover" loading="lazy">
            <PhImage v-else :size="20" />
          </span>
          <span class="truncate text-meta text-fg-muted">{{ asset.filename }}</span>
        </button>
      </li>
    </ul>
  </UiModal>
</template>
