<script setup lang="ts">
import { PhArrowLeft, PhWarningCircle } from '@phosphor-icons/vue'
import type { CmsPage } from '~/types'

/**
 * Page editor. Same editor as the blog, per README Components — `PageEditor`
 * is explicitly one component reused across both CMS resources.
 */
const route = useRoute()
const { useAdminItem } = useAdminApi()
const { formatRelative } = useFormatters()

const { item: page } = useAdminItem<CmsPage>(`page-${route.params.slug}`, `/admin/pages/${route.params.slug}`)

const body = ref('')
const title = ref('')
watchEffect(() => {
  if (!page.value) return
  body.value = page.value.body
  title.value = page.value.title
})

useHead({ title: computed(() => title.value || 'Page') })
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader :title="title || 'Page'">
      <template #actions>
        <UiButton variant="ghost" size="sm" to="/pages">
          <PhArrowLeft :size="16" />
          All pages
        </UiButton>
      </template>
    </UiPageHeader>

    <p
      v-if="page?.isPolicy"
      class="flex items-start gap-2.5 rounded-lg border border-accent/30 bg-accent-soft px-3.5 py-3 text-ui text-accent-text"
    >
      <PhWarningCircle :size="18" class="mt-px shrink-0" />
      This is a published policy customers are held to. Confirm any wording change with the
      business before saving.
    </p>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr),300px]">
      <ContentRichTextEditor v-model="body" :max-length="20000">
        <template #footer>
          <UiPermissionGate capability="content.write">
            <UiButton size="sm">Save changes</UiButton>
          </UiPermissionGate>
        </template>
      </ContentRichTextEditor>

      <aside class="flex flex-col gap-4">
        <section class="card card-pad">
          <h2 class="card-title">Details</h2>
          <UiField v-model="title" label="Title" class="mt-3" />
          <dl class="mt-3 space-y-2 border-t border-border pt-3 text-ui">
            <div>
              <dt class="text-meta text-fg-faint">Storefront path</dt>
              <dd class="font-mono text-meta text-fg">/{{ page?.slug }}</dd>
            </div>
            <div v-if="page">
              <dt class="text-meta text-fg-faint">Last updated</dt>
              <dd class="text-fg">
                {{ formatRelative(page.updatedAt) }}
                <span v-if="page.updatedByAdminName" class="text-fg-muted">by {{ page.updatedByAdminName }}</span>
              </dd>
            </div>
          </dl>
        </section>

        <section class="card card-pad">
          <h2 class="card-title">Before you save</h2>
          <p class="mt-2 text-meta text-fg-muted">
            Submitted HTML is sanitised on the server before it is stored, so pasted markup from
            a word processor is stripped back to what the storefront can safely render.
          </p>
        </section>
      </aside>
    </div>
  </div>
</template>
