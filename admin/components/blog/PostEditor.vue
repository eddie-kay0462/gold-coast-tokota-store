<script setup lang="ts">
import { PhArrowLeft, PhImage, PhLink, PhUser } from '@phosphor-icons/vue'
import type { BlogPost } from '~/types'

/**
 * Post editor — Figma node 26:1297, followed closely: the writing surface on
 * the left with its character counter and Publish action, and a right column
 * of URL & Author, Cover Image with alt text, and a 200-character meta
 * description.
 *
 * One component serves both `/blog/new` and `/blog/[id]` — they are the same
 * screen, one with an empty document.
 */
const route = useRoute()
const isNew = computed(() => route.path.endsWith('/new'))

const { useAdminItem } = useAdminApi()
const { formatRelative } = useFormatters()
const { can } = useAuth()

const post = ref<BlogPost | null>(null)
if (!isNew.value) {
  const { item } = useAdminItem<BlogPost>(`blog-${route.params.id}`, `/admin/blog/${route.params.id}`)
  watchEffect(() => { post.value = item.value })
}

const draft = reactive({
  title: '', slug: '', body: '', excerpt: '',
  coverImageAlt: '', metaDescription: '', authorName: 'Samuel Kumi-Gyau',
  isPublished: false,
})

watchEffect(() => {
  const p = post.value
  if (!p) return
  Object.assign(draft, {
    title: p.title, slug: p.slug, body: p.body, excerpt: p.excerpt,
    coverImageAlt: p.coverImageAlt, metaDescription: p.metaDescription,
    authorName: p.authorName, isPublished: p.isPublished,
  })
})

useHead({ title: computed(() => (isNew.value ? 'New post' : draft.title || 'Post')) })

const authors = [
  { value: 'Samuel Kumi-Gyau', label: 'Samuel Kumi-Gyau' },
  { value: 'Mary Seade', label: 'Mary Seade' },
  { value: 'Akosua Danso', label: 'Akosua Danso' },
]

/** The slug tracks the title until someone edits it by hand. */
const slugTouched = ref(false)
watch(() => draft.title, (t) => {
  if (slugTouched.value || !isNew.value) return
  draft.slug = t.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60)
})
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader :title="isNew ? 'New post' : 'Edit post'">
      <template #actions>
        <UiButton variant="ghost" size="sm" to="/blog">
          <PhArrowLeft :size="16" />
          All posts
        </UiButton>
      </template>
    </UiPageHeader>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr),320px]">
      <div class="flex min-w-0 flex-col gap-4">
        <input
          v-model="draft.title" type="text" placeholder="Post title"
          aria-label="Post title"
          class="w-full bg-transparent text-title font-medium text-fg-strong outline-none placeholder:text-fg-faint"
        >

        <ContentRichTextEditor v-model="draft.body" :max-length="8000">
          <template #footer>
            <span class="flex items-center gap-2">
              <UiPermissionGate capability="content.write" quiet>
                <UiButton variant="secondary" size="sm">Save draft</UiButton>
              </UiPermissionGate>
              <UiPermissionGate capability="content.publish">
                <UiButton size="sm">{{ draft.isPublished ? 'Update' : 'Publish' }}</UiButton>
                <template #denied>
                  <span class="text-meta text-fg-faint">
                    Saving a draft is fine — publishing needs an Admin.
                  </span>
                </template>
              </UiPermissionGate>
            </span>
          </template>
        </ContentRichTextEditor>
      </div>

      <aside class="flex flex-col gap-4">
        <section class="card card-pad">
          <h2 class="card-title">URL &amp; author</h2>
          <div class="mt-3 flex flex-col gap-3">
            <div class="relative">
              <PhLink :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-fg-faint" />
              <input
                v-model="draft.slug" type="text" placeholder="url-friendly-title"
                aria-label="URL slug"
                class="field min-h-[44px] pl-9 font-mono text-meta"
                @input="slugTouched = true"
              >
            </div>
            <div class="relative">
              <PhUser :size="16" class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-fg-faint" />
              <UiSelect v-model="draft.authorName" :options="authors" class="[&_select]:pl-9" />
            </div>
            <p v-if="post" class="text-meta text-fg-faint">
              Last edited {{ formatRelative(post.updatedAt) }}
            </p>
          </div>
        </section>

        <section class="card card-pad">
          <h2 class="card-title">Cover image</h2>
          <div class="mt-3 flex aspect-[3/2] items-center justify-center rounded-lg bg-bg-sunken text-fg-faint">
            <PhImage :size="28" />
          </div>
          <div class="mt-2 flex justify-end">
            <UiPermissionGate capability="media.upload" quiet>
              <UiButton variant="ghost" size="sm">Replace image</UiButton>
            </UiPermissionGate>
          </div>
          <UiField
            v-model="draft.coverImageAlt" label="Alt text" placeholder="Describe the image"
            hint="Read aloud by screen readers, and shown if the image fails to load."
            class="mt-2"
          />
        </section>

        <section class="card card-pad">
          <h2 class="card-title">Meta description</h2>
          <textarea
            v-model="draft.metaDescription" rows="4" maxlength="200"
            aria-label="Meta description"
            placeholder="Enter the meta description…"
            class="field mt-3 resize-y"
          />
          <p
            class="mt-1.5 text-meta"
            :class="200 - draft.metaDescription.length < 20 ? 'text-warning' : 'text-fg-faint'"
          >{{ draft.metaDescription.length }}/200</p>
          <p class="mt-2 text-meta text-fg-faint">
            Shown in search results and link previews. Left blank, the excerpt is used rather
            than an empty tag.
          </p>
        </section>

        <section class="card card-pad">
          <h2 class="card-title">Visibility</h2>
          <label class="mt-3 flex cursor-pointer items-start gap-2.5">
            <input
              v-model="draft.isPublished" type="checkbox" :disabled="!can('content.publish')"
              class="mt-0.5 size-4 rounded-sm border-border-strong text-accent focus:ring-accent disabled:opacity-50"
            >
            <span class="min-w-0">
              <span class="block text-ui text-fg-strong">Published</span>
              <span class="block text-meta text-fg-muted">
                Published posts appear on the storefront and in the sitemap immediately.
              </span>
            </span>
          </label>
        </section>
      </aside>
    </div>
  </div>
</template>
