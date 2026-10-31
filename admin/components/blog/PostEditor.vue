<script setup lang="ts">
import { PhArrowLeft, PhImage, PhLink, PhTrash, PhUser } from '@phosphor-icons/vue'
import type { BlogPost, MediaAsset } from '~/types'

/**
 * Post editor, Figma node 26:1297, followed closely: the writing surface on
 * the left with its character counter and Publish action, and a right column
 * of URL & Author, Cover Image with alt text, and a 200-character meta
 * description.
 *
 * One component serves both `/blog/new` and `/blog/[id]`; they are the same
 * screen, one with an empty document.
 */
const route = useRoute()
const isNew = computed(() => route.path.endsWith('/new'))

const { useAdminItem, adminFetch } = useAdminApi()
const { formatRelative } = useFormatters()
const { can } = useAuth()
const toast = useToast()

const post = ref<BlogPost | null>(null)
if (!isNew.value) {
  const { item } = useAdminItem<BlogPost>(`blog-${route.params.id}`, `/admin/blog/${route.params.id}`)
  watchEffect(() => { post.value = item.value })
}

const draft = reactive({
  title: '', slug: '', body: '', excerpt: '',
  coverImage: null as string | null,
  coverImageAlt: '', metaDescription: '', authorName: 'Samuel Kumi-Gyau',
  isPublished: false,
})

watchEffect(() => {
  const p = post.value
  if (!p) return
  Object.assign(draft, {
    title: p.title, slug: p.slug, body: p.body, excerpt: p.excerpt,
    coverImage: p.coverImage, coverImageAlt: p.coverImageAlt, metaDescription: p.metaDescription,
    authorName: p.authorName, isPublished: p.isPublished,
  })
})

// --- Saving -----------------------------------------------------------------
// `POST /admin/blog` for a new post, `PUT /admin/blog/{id}` after. Publishing
// takes `content.publish`, which the API checks as well; a draft saved by
// Staff keeps whatever state the post already had.
const saving = ref<'draft' | 'publish' | 'delete' | null>(null)
const error = ref<string | null>(null)
const valid = computed(() => !!draft.title.trim() && !!draft.body.replace(/<[^>]*>/g, '').trim())

async function save(publish: boolean | null) {
  if (!valid.value) {
    error.value = 'A post needs a title and some text.'
    return
  }
  saving.value = publish ? 'publish' : 'draft'
  error.value = null
  const body: Record<string, unknown> = {
    title: draft.title.trim(),
    slug: draft.slug.trim() || null,
    body: draft.body,
    excerpt: draft.excerpt.trim() || null,
    cover_image: draft.coverImage,
    cover_image_alt: draft.coverImageAlt.trim() || null,
    meta_description: draft.metaDescription.trim() || null,
    author: draft.authorName.trim() || null,
  }
  if (publish !== null) body.is_published = publish
  try {
    const { data } = post.value
      ? await adminFetch<BlogPost>(`/admin/blog/${post.value.id}`, { method: 'PUT', body })
      : await adminFetch<BlogPost>('/admin/blog', { method: 'POST', body })
    post.value = data
    toast.success(
      data.isPublished ? (publish ? 'Published' : 'Saved') : 'Draft saved',
      data.isPublished ? 'Live on the storefront now.' : 'Not on the storefront until it’s published.',
    )
    if (isNew.value) await navigateTo(`/blog/${data.id}`, { replace: true })
  } catch (err) {
    error.value = failureMessage(err)
  } finally {
    saving.value = null
  }
}

const confirmingDelete = ref(false)
async function deletePost() {
  if (!post.value) return
  saving.value = 'delete'
  try {
    await adminFetch(`/admin/blog/${post.value.id}`, { method: 'DELETE' })
    toast.success('Post deleted', post.value.title)
    await navigateTo('/blog')
  } catch (err) {
    error.value = failureMessage(err, 'Not deleted. Try again in a moment.')
    confirmingDelete.value = false
  } finally {
    saving.value = null
  }
}

// --- Cover ------------------------------------------------------------------
// Picked from the media library; the post stores the image's URL.
const pickingCover = ref(false)
function useCover(asset: MediaAsset) {
  draft.coverImage = asset.url
  if (!draft.coverImageAlt.trim()) draft.coverImageAlt = asset.altText
}

useHead({ title: computed(() => (isNew.value ? 'New post' : draft.title || 'Post')) })

// Suggestions only: the byline is free text, so anyone can be credited.
const authors = ['Samuel Kumi-Gyau', 'Mary Seade', 'Akosua Danso']

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
        <template v-if="post && can('content.delete')">
          <UiButton v-if="!confirmingDelete" variant="ghost" size="sm" class="text-danger" @click="confirmingDelete = true">
            <PhTrash :size="16" />
            Delete
          </UiButton>
          <template v-else>
            <UiButton variant="secondary" size="sm" @click="confirmingDelete = false">Keep it</UiButton>
            <UiButton variant="danger" size="sm" :loading="saving === 'delete'" @click="deletePost">Delete this post</UiButton>
          </template>
        </template>
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
                <UiButton
                  v-if="!post?.isPublished" variant="secondary" size="sm"
                  :loading="saving === 'draft'" @click="save(can('content.publish') ? false : null)"
                >Save draft</UiButton>
                <UiButton
                  v-else-if="can('content.publish')" variant="secondary" size="sm"
                  :loading="saving === 'draft'" @click="save(false)"
                >Unpublish</UiButton>
              </UiPermissionGate>
              <UiPermissionGate capability="content.publish">
                <UiButton size="sm" :loading="saving === 'publish'" @click="save(true)">
                  {{ post?.isPublished ? 'Update' : 'Publish' }}
                </UiButton>
                <template #denied>
                  <span class="text-meta text-fg-faint">
                    You can save a draft, but publishing needs an Admin.
                  </span>
                </template>
              </UiPermissionGate>
            </span>
          </template>
        </ContentRichTextEditor>
        <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ error }}</p>
        <UiField
          v-model="draft.excerpt" label="Excerpt" type="textarea" :rows="2"
          hint="Optional. The short summary on the blog list; up to 500 characters."
        />
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
              <input
                v-model="draft.authorName" type="text" list="post-authors" aria-label="Author"
                placeholder="Who wrote it" class="field min-h-[44px] pl-9"
              >
              <datalist id="post-authors">
                <option v-for="a in authors" :key="a" :value="a" />
              </datalist>
            </div>
            <p v-if="post" class="text-meta text-fg-faint">
              Last edited {{ formatRelative(post.updatedAt) }}
            </p>
          </div>
        </section>

        <section class="card card-pad">
          <h2 class="card-title">Cover image</h2>
          <div class="mt-3 flex aspect-[3/2] items-center justify-center overflow-hidden rounded-lg bg-bg-sunken text-fg-faint">
            <img v-if="draft.coverImage" :src="draft.coverImage" :alt="draft.coverImageAlt" class="size-full object-cover">
            <PhImage v-else :size="28" />
          </div>
          <div v-if="can('content.write')" class="mt-2 flex justify-end gap-2">
            <UiButton v-if="draft.coverImage" variant="ghost" size="sm" @click="draft.coverImage = null">Remove</UiButton>
            <UiButton variant="ghost" size="sm" @click="pickingCover = true">
              {{ draft.coverImage ? 'Replace image' : 'Choose image' }}
            </UiButton>
          </div>
          <ContentMediaPicker v-model:open="pickingCover" @select="useCover" />
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
          <p class="mt-3 flex items-center gap-2 text-ui text-fg-strong">
            <UiBadge :tone="post?.isPublished ? 'success' : 'neutral'" dot>
              {{ post?.isPublished ? 'Published' : 'Draft' }}
            </UiBadge>
          </p>
          <p class="mt-2 text-meta text-fg-muted">
            Published posts appear on the storefront and in the sitemap straight away. Publish
            and Unpublish change it.
          </p>
        </section>
      </aside>
    </div>
  </div>
</template>
