<script setup lang="ts">
import { PhArrowLeft, PhArrowSquareOut, PhCaretDown } from '@phosphor-icons/vue'
import type { AboutContent, AboutPageAdmin } from '~/types'

/**
 * Content › Pages › About: every section of the storefront's About page.
 *
 * The page is designed section by section, so this is a form per section
 * rather than one rich-text body: headings, paragraphs, lists (figures,
 * materials, team, experiences, videos, recognition, slogans), images from
 * the media library, and a switch to hide any section. One Save sends the
 * whole page; the API (App\Support\AboutPage) validates it and keeps only
 * known fields. Takes the /pages/about route ahead of the generic editor.
 */
useHead({ title: 'About page' })

const { useAdminItem, adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()
const { formatRelative } = useFormatters()
const config = useRuntimeConfig()

const { item, refresh, pending } = useAdminItem<AboutPageAdmin>('about-page', '/admin/pages/about')

const canEdit = computed(() => can('content.write'))
const draft = ref<AboutContent | null>(null)
watch(item, (value) => {
  // Only the real payload: a fixture standing in for this path has no content.
  if (value?.content?.hero) draft.value = structuredClone(toRaw(value.content))
}, { immediate: true })

const dirty = computed(() => !!draft.value && !!item.value && JSON.stringify(draft.value) !== JSON.stringify(item.value.content))
const saving = ref(false)
const errors = ref<string[]>([])

async function save() {
  if (!draft.value) return
  saving.value = true
  errors.value = []
  try {
    await adminFetch('/admin/pages/about', { method: 'PUT', body: { content: draft.value } })
    await refresh()
    toast.success('About page saved', 'It’s live on the storefront now.')
  } catch (err: unknown) {
    const e = err as { statusCode?: number, data?: { errors?: Record<string, string[]>, message?: string } }
    if (e.statusCode === 401 || e.statusCode === 419) return
    errors.value = Object.entries(e.data?.errors ?? {}).map(([field, [message]]) => `${label(field)}: ${message}`)
    if (!errors.value.length) errors.value = [e.data?.message ?? 'Saving failed. Try again in a moment.']
  } finally {
    saving.value = false
  }
}

function discard() {
  if (item.value?.content) draft.value = structuredClone(toRaw(item.value.content))
  errors.value = []
}

/** "content.figures.items.0.href" → "Figures, item 1, href". */
function label(field: string) {
  return field.replace(/^content\./, '').split('.').map((part) => /^\d+$/.test(part) ? `item ${Number(part) + 1}` : part).join(', ')
}

const SECTIONS: { key: keyof AboutContent, title: string, hint: string }[] = [
  { key: 'hero', title: 'Hero', hint: 'The black band at the top.' },
  { key: 'statement', title: 'Statement', hint: 'The one line under the hero.' },
  { key: 'story', title: 'Our story', hint: 'The founding story and a quote.' },
  { key: 'figures', title: 'Figures', hint: 'The numbers row. Give each a source and date.' },
  { key: 'materials', title: 'Materials', hint: 'What the pairs are made from.' },
  { key: 'people', title: 'People', hint: 'The team photo, its text and named roles.' },
  { key: 'ahenema', title: 'The ahenema', hint: 'The two-up block with a product photo.' },
  { key: 'experiences', title: 'Experiences', hint: 'Workshops, on black. Times should match Bookings.' },
  { key: 'watch', title: 'From the workshop', hint: 'The Instagram and YouTube carousel.' },
  { key: 'slogans', title: 'Slogan ticker', hint: 'The phrases that scroll across the page.' },
  { key: 'recognition', title: 'Recognition', hint: 'Programmes and awards, each with a link.' },
  { key: 'mission', title: 'Mission & vision', hint: 'Where we’re going.' },
  { key: 'socials', title: 'Social links', hint: 'The carousel’s Instagram and YouTube links.' },
]

function chosenUrl(section: 'hero' | 'people' | 'ahenema') {
  const saved = item.value?.content?.[section]
  return saved && saved.imageId === draft.value?.[section].imageId ? saved.imageUrl : undefined
}
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="About page" description="Everything on the storefront’s About page, section by section.">
      <template #actions>
        <UiButton variant="ghost" size="sm" to="/pages"><PhArrowLeft :size="16" />All pages</UiButton>
        <UiButton variant="secondary" size="sm" :to="`${config.public.storefrontUrl}/about`" target="_blank" rel="noopener">
          <PhArrowSquareOut :size="16" />View page
        </UiButton>
      </template>
    </UiPageHeader>

    <p v-if="pending && !draft" class="text-ui text-fg-muted">Loading the page…</p>
    <p v-else-if="!draft" class="rounded-lg border border-border px-3.5 py-3 text-ui text-fg-muted">
      The About page’s content didn’t load. Check the API is running, then refresh.
    </p>

    <template v-else>
      <!-- Save bar: stays in view while scrolling a long page. -->
      <div class="sticky top-0 z-10 -mx-1 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-bg-elevated px-4 py-3 shadow-sm">
        <p class="text-meta text-fg-muted">
          <template v-if="dirty">Unsaved changes.</template>
          <template v-else-if="item?.updatedAt">Saved {{ formatRelative(item.updatedAt) }}<template v-if="item.updatedBy"> by {{ item.updatedBy }}</template>.</template>
          <template v-else>Showing the designed copy; nothing saved yet.</template>
        </p>
        <UiPermissionGate capability="content.write" quiet>
          <div class="flex gap-2">
            <UiButton variant="ghost" size="sm" :disabled="!dirty" @click="discard">Discard</UiButton>
            <UiButton size="sm" :disabled="!dirty" :loading="saving" @click="save">Save page</UiButton>
          </div>
        </UiPermissionGate>
      </div>

      <div v-if="errors.length" class="rounded-lg border border-danger/30 bg-danger-soft px-3.5 py-3 text-ui text-danger" role="alert">
        <p class="font-medium">Not saved. Fix these first:</p>
        <ul class="mt-1 list-disc pl-5">
          <li v-for="error in errors" :key="error">{{ error }}</li>
        </ul>
      </div>

      <details v-for="section in SECTIONS" :key="section.key" class="card group" :open="section.key === 'hero'">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 md:p-5">
          <span class="min-w-0">
            <span class="card-title block">{{ section.title }}</span>
            <span class="block text-meta text-fg-muted">{{ section.hint }}</span>
          </span>
          <span class="flex shrink-0 items-center gap-3">
            <UiBadge v-if="'visible' in draft[section.key]" :tone="(draft[section.key] as { visible: boolean }).visible ? 'success' : 'neutral'">
              {{ (draft[section.key] as { visible: boolean }).visible ? 'Shown' : 'Hidden' }}
            </UiBadge>
            <PhCaretDown :size="16" class="text-fg-faint transition-transform group-open:rotate-180" />
          </span>
        </summary>

        <fieldset class="flex flex-col gap-4 border-t border-border p-4 md:p-5" :disabled="!canEdit">
          <label v-if="'visible' in draft[section.key]" class="flex items-center gap-2 text-ui text-fg-strong">
            <input v-model="(draft[section.key] as { visible: boolean }).visible" type="checkbox" class="size-4">
            Show this section on the page
          </label>

          <!-- Hero -->
          <template v-if="section.key === 'hero'">
            <UiField v-model="draft.hero.eyebrow" label="Eyebrow" hint="The small line above the heading." />
            <UiField v-model="draft.hero.heading" label="Heading" type="textarea" :rows="2" hint="A new line starts a new line on the page." />
            <UiField v-model="draft.hero.lede" label="Introduction" type="textarea" :rows="3" />
            <ContentImageField v-model:image-id="draft.hero.imageId" v-model:alt="draft.hero.imageAlt" :default-src="draft.hero.image" :current-url="chosenUrl('hero')" :disabled="!canEdit" />
          </template>

          <template v-else-if="section.key === 'statement'">
            <UiField v-model="draft.statement.text" label="Statement" type="textarea" :rows="2" />
          </template>

          <template v-else-if="section.key === 'story'">
            <UiField v-model="draft.story.eyebrow" label="Eyebrow" />
            <UiField v-model="draft.story.heading" label="Heading" />
            <p class="field-label">Paragraphs</p>
            <ContentListEditor v-model="draft.story.paragraphs" noun="paragraph" :blank="() => ''" :max="8" :disabled="!canEdit">
              <template #default="{ index }">
                <UiField v-model="draft.story.paragraphs[index]" type="textarea" :rows="3" :aria-label="`Paragraph ${index + 1}`" />
              </template>
            </ContentListEditor>
            <UiField v-model="draft.story.quote" label="Quote" type="textarea" :rows="2" hint="Leave empty to show no quote." />
            <div class="grid gap-3 sm:grid-cols-3">
              <UiField v-model="draft.story.quoteBy" label="Who said it" />
              <UiField v-model="draft.story.quoteSource" label="Where and when" />
              <UiField v-model="draft.story.quoteUrl" label="Link to the source" placeholder="https://" />
            </div>
          </template>

          <template v-else-if="section.key === 'figures'">
            <ContentListEditor v-model="draft.figures.items" noun="figure" :blank="() => ({ value: '', label: '', source: '', href: '' })" :max="8" :disabled="!canEdit">
              <template #default="{ item: figure }">
                <div class="grid gap-3 sm:grid-cols-[8rem,1fr]">
                  <UiField v-model="figure.value" label="Number" placeholder="2,500+" />
                  <UiField v-model="figure.label" label="What it counts" />
                  <UiField v-model="figure.source" label="Source and date" placeholder="Business Beat, Feb 2025" />
                  <UiField v-model="figure.href" label="Link to the source" placeholder="https://" />
                </div>
              </template>
            </ContentListEditor>
          </template>

          <template v-else-if="section.key === 'materials'">
            <UiField v-model="draft.materials.eyebrow" label="Eyebrow" />
            <UiField v-model="draft.materials.heading" label="Heading" />
            <UiField v-model="draft.materials.body" label="Text" type="textarea" :rows="3" />
            <ContentListEditor v-model="draft.materials.items" noun="material" :blank="() => ({ name: '', body: '' })" :max="12" :disabled="!canEdit">
              <template #default="{ item: material }">
                <UiField v-model="material.name" label="Material" />
                <UiField v-model="material.body" label="Description" type="textarea" :rows="2" class="mt-2" />
              </template>
            </ContentListEditor>
          </template>

          <template v-else-if="section.key === 'people'">
            <UiField v-model="draft.people.eyebrow" label="Eyebrow" />
            <UiField v-model="draft.people.heading" label="Heading" />
            <UiField v-model="draft.people.body" label="Text" type="textarea" :rows="3" />
            <ContentImageField v-model:image-id="draft.people.imageId" v-model:alt="draft.people.imageAlt" :default-src="draft.people.image" :current-url="chosenUrl('people')" :disabled="!canEdit" />
            <UiField v-model="draft.people.caption" label="Photo caption" />
            <p class="field-label">Named team</p>
            <ContentListEditor v-model="draft.people.team" noun="person" :blank="() => ({ role: '', name: '' })" :max="12" :disabled="!canEdit">
              <template #default="{ item: person }">
                <div class="grid gap-3 sm:grid-cols-2">
                  <UiField v-model="person.name" label="Name" />
                  <UiField v-model="person.role" label="Role" />
                </div>
              </template>
            </ContentListEditor>
          </template>

          <template v-else-if="section.key === 'ahenema'">
            <UiField v-model="draft.ahenema.eyebrow" label="Eyebrow" />
            <UiField v-model="draft.ahenema.heading" label="Heading" type="textarea" :rows="2" hint="A new line starts a new line on the page." />
            <UiField v-model="draft.ahenema.body" label="Text" type="textarea" :rows="4" />
            <ContentImageField v-model:image-id="draft.ahenema.imageId" v-model:alt="draft.ahenema.imageAlt" :default-src="draft.ahenema.image" :current-url="chosenUrl('ahenema')" :disabled="!canEdit" />
          </template>

          <template v-else-if="section.key === 'experiences'">
            <UiField v-model="draft.experiences.eyebrow" label="Eyebrow" />
            <UiField v-model="draft.experiences.heading" label="Heading" />
            <UiField v-model="draft.experiences.body" label="Text" type="textarea" :rows="2" />
            <ContentListEditor v-model="draft.experiences.items" noun="experience" :blank="() => ({ name: '', when: '', detail: '' })" :max="8" :disabled="!canEdit">
              <template #default="{ item: experience }">
                <div class="grid gap-3 sm:grid-cols-2">
                  <UiField v-model="experience.name" label="Name" />
                  <UiField v-model="experience.when" label="When" placeholder="Every Saturday · 10:00 AM – 1:00 PM" />
                </div>
                <UiField v-model="experience.detail" label="Detail" class="mt-2" />
              </template>
            </ContentListEditor>
          </template>

          <template v-else-if="section.key === 'watch'">
            <UiField v-model="draft.watch.eyebrow" label="Eyebrow" />
            <UiField v-model="draft.watch.heading" label="Heading" />
            <UiField v-model="draft.watch.body" label="Text" type="textarea" :rows="2" />
            <p class="text-meta text-fg-muted">
              For YouTube, the ID is the part after <code>v=</code> or <code>/shorts/</code>. For Instagram, the code after
              <code>/reel/</code> or <code>/p/</code>.
            </p>
            <ContentListEditor v-model="draft.watch.posts" noun="video" :blank="() => ({ platform: 'youtube', id: '', title: '', meta: '', shape: 'landscape', thumb: null })" :max="30" :disabled="!canEdit">
              <template #default="{ item: post }">
                <div class="grid gap-3 sm:grid-cols-3">
                  <label class="flex flex-col gap-1">
                    <span class="field-label">Platform</span>
                    <select v-model="post.platform" class="field min-h-[44px]">
                      <option value="youtube">YouTube</option>
                      <option value="instagram">Instagram</option>
                    </select>
                  </label>
                  <UiField v-model="post.id" label="Video ID or reel code" placeholder="sPsziRAI9JE" />
                  <label class="flex flex-col gap-1">
                    <span class="field-label">Shape</span>
                    <select v-model="post.shape" class="field min-h-[44px]">
                      <option value="portrait">Portrait (Short or reel)</option>
                      <option value="landscape">Landscape (16:9)</option>
                    </select>
                  </label>
                </div>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                  <UiField v-model="post.title" label="Title" />
                  <UiField v-model="post.meta" label="Who and how long" placeholder="Adom TV · 7 min" />
                </div>
                <label v-if="post.platform === 'youtube'" class="mt-2 flex flex-col gap-1">
                  <span class="field-label">Thumbnail</span>
                  <select v-model="post.thumb" class="field min-h-[44px]">
                    <option :value="null">Standard (always available)</option>
                    <option value="maxresdefault">High resolution</option>
                    <option value="oardefault">Portrait (some Shorts have one)</option>
                  </select>
                </label>
              </template>
            </ContentListEditor>
          </template>

          <template v-else-if="section.key === 'slogans'">
            <ContentListEditor v-model="draft.slogans.items" noun="phrase" :blank="() => ''" :max="6" :disabled="!canEdit">
              <template #default="{ index }">
                <UiField v-model="draft.slogans.items[index]" :aria-label="`Phrase ${index + 1}`" />
              </template>
            </ContentListEditor>
          </template>

          <template v-else-if="section.key === 'recognition'">
            <UiField v-model="draft.recognition.eyebrow" label="Eyebrow" />
            <UiField v-model="draft.recognition.heading" label="Heading" />
            <ContentListEditor v-model="draft.recognition.items" noun="entry" :blank="() => ({ name: '', detail: '', date: '', href: '' })" :max="20" :disabled="!canEdit">
              <template #default="{ item: entry }">
                <div class="grid gap-3 sm:grid-cols-2">
                  <UiField v-model="entry.name" label="Organisation" />
                  <UiField v-model="entry.date" label="Date" placeholder="Oct 2025" />
                </div>
                <UiField v-model="entry.detail" label="What it was" class="mt-2" />
                <UiField v-model="entry.href" label="Link to the source" placeholder="https://" class="mt-2" />
              </template>
            </ContentListEditor>
          </template>

          <template v-else-if="section.key === 'mission'">
            <UiField v-model="draft.mission.heading" label="Heading" />
            <UiField v-model="draft.mission.lede" label="Line under the heading" />
            <UiField v-model="draft.mission.mission" label="Our mission" type="textarea" :rows="4" />
            <UiField v-model="draft.mission.vision" label="Our vision" type="textarea" :rows="4" />
          </template>

          <template v-else-if="section.key === 'socials'">
            <UiField v-model="draft.socials.instagram" label="Instagram" placeholder="https://www.instagram.com/…" />
            <UiField v-model="draft.socials.youtube" label="YouTube" placeholder="https://www.youtube.com/@…" />
          </template>
        </fieldset>
      </details>
    </template>
  </div>
</template>
