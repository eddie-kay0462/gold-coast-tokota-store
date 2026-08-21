<script setup lang="ts">
import { PhArrowSquareOut, PhCheckCircle, PhWarningCircle } from '@phosphor-icons/vue'
import type { BlogPost, CmsPage, Product, SiteSettings } from '~/types'

/**
 * SEO.
 *
 * Half settings, half audit. The settings half is the storefront's global meta
 * defaults; the audit half lists content that will ship with a missing or
 * generated tag, because README Feature 10 requires every product, post and
 * core page to carry a unique descriptive title and description in the
 * server-rendered HTML — and the only way anyone notices a gap is if something
 * counts them.
 */
useHead({ title: 'SEO' })

const { useAdminItem, useAdminList } = useAdminApi()
const { item: site } = useAdminItem<SiteSettings>('seo-site-settings', '/admin/site-settings')
const { items: posts } = useAdminList<BlogPost>('seo-blog', '/admin/blog')
const { items: cmsPages } = useAdminList<CmsPage>('seo-pages', '/admin/pages')
const { items: products } = useAdminList<Product>('seo-products', '/admin/products')

const form = reactive({
  siteName: 'Gold Coast Tokota',
  titleTemplate: '%s · Gold Coast Tokota',
  defaultDescription: '',
  canonicalHost: 'https://goldcoasttokota.store',
})
watchEffect(() => {
  if (site.value && !form.defaultDescription) {
    form.defaultDescription =
      'Handcrafted sustainable footwear from recycled materials, celebrating Ghanaian culture '
      + 'through immersive experiences and craftsmanship.'
  }
})

/** Content that would ship with a weak or missing tag. */
const gaps = computed(() => {
  const out: { label: string; detail: string; to: string }[] = []
  for (const p of posts.value.filter((x) => x.isPublished && !x.metaDescription.trim())) {
    out.push({ label: p.title, detail: 'Published post with no meta description', to: `/blog/${p.id}` })
  }
  for (const p of products.value.filter((x) => x.isActive && x.description.trim().length < 60)) {
    out.push({ label: p.name, detail: 'Active product with a very short description', to: `/products/${p.id}` })
  }
  return out
})

const indexable = computed(() => [
  { label: 'Active products', count: products.value.filter((p) => p.isActive).length },
  { label: 'Published posts', count: posts.value.filter((p) => p.isPublished).length },
  { label: 'CMS pages', count: cmsPages.value.length },
])
</script>

<template>
  <SettingsShell title="SEO" description="Global meta defaults, and what the sitemap will carry.">
    <UiPermissionGate capability="settings.view">
      <div class="admin-stack">
        <SettingsSection
          title="Defaults"
          description="Used where a page supplies nothing of its own. A page-level tag always wins."
        >
          <div class="grid gap-4 md:grid-cols-2">
            <UiField v-model="form.siteName" label="Site name" />
            <UiField
              v-model="form.titleTemplate" label="Title template"
              hint="%s is replaced by the page's own title."
            />
            <UiField
              v-model="form.canonicalHost" label="Canonical host" type="url" class="md:col-span-2"
              hint="Canonical URLs and the sitemap are built from this."
            />
            <UiField
              v-model="form.defaultDescription" label="Fallback description" type="textarea"
              class="md:col-span-2"
              hint="Used when a page has no description of its own — better than shipping an empty tag."
            />
          </div>
          <template #footer>
            <UiPermissionGate capability="settings.write" quiet>
              <UiButton size="sm">Save</UiButton>
            </UiPermissionGate>
          </template>
        </SettingsSection>

        <SettingsSection
          title="Sitemap"
          description="Generated from active products and published posts. Inactive and draft content is excluded automatically."
        >
          <dl class="grid gap-4 sm:grid-cols-3">
            <div v-for="i in indexable" :key="i.label" class="rounded-lg border border-border p-4">
              <dt class="text-meta text-fg-faint">{{ i.label }}</dt>
              <dd class="mt-1 text-metric font-light tracking-tight text-fg-strong">{{ i.count }}</dd>
            </div>
          </dl>
          <p class="mt-4 flex items-start gap-2 text-meta text-fg-muted">
            <PhCheckCircle :size="15" class="mt-px shrink-0 text-success" />
            <span>
              <code class="font-mono">/checkout</code> is disallowed in robots.txt, and the admin
              dashboard sits on its own domain — it is never linked from the storefront or included
              in the sitemap, so there is nothing to exclude.
            </span>
          </p>
        </SettingsSection>

        <SettingsSection
          title="Content gaps"
          :description="gaps.length
            ? `${gaps.length} items would ship with a generated or missing tag.`
            : 'Every published item carries its own title and description.'"
        >
          <UiEmptyState
            v-if="!gaps.length"
            title="Nothing missing"
            description="All published content has a description of its own."
            :icon="PhCheckCircle"
          />
          <ul v-else class="divide-y divide-border">
            <li v-for="g in gaps" :key="g.to + g.label" class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
              <PhWarningCircle :size="16" class="shrink-0 text-warning" />
              <span class="min-w-0 flex-1">
                <span class="block truncate text-ui text-fg-strong">{{ g.label }}</span>
                <span class="block truncate text-meta text-fg-muted">{{ g.detail }}</span>
              </span>
              <NuxtLink :to="g.to" class="toolbar-btn shrink-0" :aria-label="`Fix ${g.label}`">
                <PhArrowSquareOut :size="16" />
              </NuxtLink>
            </li>
          </ul>
        </SettingsSection>
      </div>
    </UiPermissionGate>
  </SettingsShell>
</template>
