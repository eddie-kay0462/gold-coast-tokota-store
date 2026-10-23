<script setup lang="ts">
import { PhArrowSquareOut, PhImage } from '@phosphor-icons/vue'
import type { MediaAsset, MegaMenuAdmin, MegaMenuSlot } from '~/types'

/**
 * Content › Menus: the two image tiles in each storefront mega menu (header
 * row 3: Best Sellers, Sandals, Ahenema, Sale).
 *
 * The menus' links aren't edited here. The API builds them from the
 * catalogue (App\Support\MegaMenus), so they only ever offer styles and
 * departments that have products. They're shown for reference.
 *
 * A slot with no image shows that menu's newest product photo on the
 * storefront (best-selling, for Best Sellers), so leaving both empty is fine.
 */
useHead({ title: 'Menus' })

const { useAdminList, adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()
const { items: menus, pending, refresh } = useAdminList<MegaMenuAdmin>('admin-menus', '/admin/menus')

const canEdit = computed(() => can('content.write'))

/** Editable copies of each menu's slots, keyed by menu. */
const drafts = reactive<Record<string, MegaMenuSlot[]>>({})
watch(menus, (list) => {
  for (const menu of list) drafts[menu.key] = menu.slots.map((slot) => ({ ...slot }))
}, { immediate: true })

const saving = ref<string | null>(null)
const errors = reactive<Record<string, string | null>>({})

const picking = ref<{ key: string, position: number } | null>(null)
const pickerOpen = computed({
  get: () => picking.value !== null,
  set: (open) => { if (!open) picking.value = null },
})

function slotOf(key: string, position: number): MegaMenuSlot | undefined {
  return drafts[key]?.find((slot) => slot.position === position)
}

function onPick(asset: MediaAsset) {
  if (!picking.value) return
  const slot = slotOf(picking.value.key, picking.value.position)
  if (!slot) return
  slot.mediaAsset = { id: asset.id, url: asset.url, filename: asset.filename, altText: asset.altText }
  // A sensible start the editor can change: the menu's own page.
  if (!slot.link) slot.link = menus.value.find((m) => m.key === picking.value!.key)?.to ?? '/shop'
}

function clearSlot(key: string, position: number) {
  const slot = slotOf(key, position)
  if (slot) Object.assign(slot, { mediaAsset: null, caption: '', link: '' })
}

function isDirty(menu: MegaMenuAdmin): boolean {
  return JSON.stringify(drafts[menu.key]) !== JSON.stringify(menu.slots)
}

async function save(menu: MegaMenuAdmin) {
  saving.value = menu.key
  errors[menu.key] = null
  try {
    await adminFetch(`/admin/menus/${menu.key}`, {
      method: 'PUT',
      body: {
        slots: (drafts[menu.key] ?? []).map((slot) => ({
          position: slot.position,
          media_asset_id: slot.mediaAsset?.id ?? null,
          caption: slot.caption.trim(),
          link: slot.link.trim(),
        })),
      },
    })
    await refresh()
    toast.success(`${menu.label} menu saved`, 'The storefront shows it within a minute.')
  } catch (err: unknown) {
    const e = err as { statusCode?: number, data?: { errors?: Record<string, string[]>, message?: string } }
    if (e.statusCode === 401 || e.statusCode === 419) return
    errors[menu.key] = Object.values(e.data?.errors ?? {})[0]?.[0] ?? e.data?.message ?? 'Saving failed. Try again in a moment.'
  } finally {
    saving.value = null
  }
}

/** What the storefront shows in this slot when nothing is chosen. */
function automatic(menu: MegaMenuAdmin, position: number) {
  const chosenBefore = menu.slots.filter((s) => s.position < position && s.mediaAsset).length
  const autos = menu.showing.filter((tile) => !tile.chosen)
  return autos[position - 1 - chosenBefore] ?? null
}
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader
      title="Menus"
      description="The image tiles in the storefront's header menus. The links are built from the catalogue."
    />

    <p v-if="pending && !menus.length" class="text-ui text-fg-muted">Loading menus…</p>

    <section v-for="menu in menus" :key="menu.key" class="card card-pad">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <h2 class="card-title">{{ menu.label }}</h2>
          <p class="text-meta text-fg-muted">
            Header › {{ menu.label }} ·
            <a :href="`${$config.public.storefrontUrl}${menu.to}`" target="_blank" rel="noopener" class="inline-flex items-center gap-1 underline hover:no-underline">
              {{ menu.to }}<PhArrowSquareOut :size="12" />
            </a>
          </p>
        </div>
        <UiPermissionGate capability="content.write" quiet>
          <UiButton size="sm" :disabled="!isDirty(menu)" :loading="saving === menu.key" @click="save(menu)">
            Save
          </UiButton>
        </UiPermissionGate>
      </div>

      <p v-if="errors[menu.key]" class="mt-3 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2 text-ui text-danger" role="alert">
        {{ errors[menu.key] }}
      </p>

      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div v-for="slot in drafts[menu.key]" :key="slot.position" class="flex flex-col gap-3 rounded-lg border border-border p-3">
          <div class="flex items-center justify-between gap-2">
            <p class="text-ui text-fg-strong">Tile {{ slot.position }}</p>
            <UiBadge :tone="slot.mediaAsset ? 'info' : 'neutral'">
              {{ slot.mediaAsset ? 'Chosen' : 'Automatic' }}
            </UiBadge>
          </div>

          <div class="flex aspect-[4/3] w-full items-center justify-center overflow-hidden rounded-md bg-bg-sunken text-fg-faint">
            <img v-if="slot.mediaAsset?.url" :src="slot.mediaAsset.url" :alt="slot.mediaAsset.altText" class="size-full object-cover">
            <img
              v-else-if="automatic(menu, slot.position)"
              :src="automatic(menu, slot.position)!.image"
              :alt="automatic(menu, slot.position)!.alt"
              class="size-full object-cover opacity-60"
            >
            <PhImage v-else :size="24" />
          </div>

          <p v-if="!slot.mediaAsset" class="text-meta text-fg-muted">
            <template v-if="automatic(menu, slot.position)">
              Showing {{ automatic(menu, slot.position)!.label }}, the menu's own product photo, until you choose an image.
            </template>
            <template v-else>Empty on the storefront: this menu has no product photos to show yet.</template>
          </p>

          <template v-if="canEdit">
            <div class="flex gap-2">
              <UiButton variant="secondary" size="sm" @click="picking = { key: menu.key, position: slot.position }">
                {{ slot.mediaAsset ? 'Change image' : 'Choose image' }}
              </UiButton>
              <UiButton v-if="slot.mediaAsset" variant="ghost" size="sm" @click="clearSlot(menu.key, slot.position)">
                Use automatic
              </UiButton>
            </div>

            <template v-if="slot.mediaAsset">
              <UiField v-model="slot.caption" label="Caption" placeholder="e.g. New season ahenema" required hint="Shown under the image, in capitals." />
              <div>
                <UiField
                  v-model="slot.link"
                  label="Links to"
                  placeholder="/shop?type=ahenema"
                  required
                  hint="A page on the storefront. Pick one of the menu's links, or type a path such as /shop/sikapa."
                  :list="`links-${menu.key}`"
                />
                <datalist :id="`links-${menu.key}`">
                  <option v-for="option in menu.linkOptions" :key="option.to" :value="option.to">{{ option.label }}</option>
                </datalist>
              </div>
            </template>
          </template>
          <p v-else-if="slot.mediaAsset" class="text-meta text-fg-muted">
            “{{ slot.caption }}” → {{ slot.link }}
          </p>
        </div>
      </div>

      <details class="mt-4">
        <summary class="cursor-pointer text-meta text-fg-muted">Links in this menu (from the catalogue)</summary>
        <div class="mt-2 grid gap-3 sm:grid-cols-3">
          <div v-for="column in menu.columns" :key="column.heading">
            <p class="text-meta font-medium text-fg-strong">{{ column.heading }}</p>
            <ul class="mt-1 space-y-0.5 text-meta text-fg-muted">
              <li v-for="link in column.links" :key="link.to + link.label">{{ link.label }} <span class="text-fg-faint">{{ link.to }}</span></li>
            </ul>
          </div>
        </div>
      </details>
    </section>

    <ContentMediaPicker v-model:open="pickerOpen" @select="onPick" />
  </div>
</template>
