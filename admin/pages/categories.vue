<script setup lang="ts">
import { PhPencilSimple, PhPlus, PhTag } from '@phosphor-icons/vue'
import type { Category } from '~/types'

/**
 * Categories: add, rename, delete (`/admin/categories`). The API only deletes
 * an empty one, so a category's products are moved first, on their own pages.
 */
useHead({ title: 'Categories' })

const { adminFetch } = useAdminApi()
const { can } = useAuth()
const toast = useToast()
const { categories, pending, refresh } = useCategories()

const editing = ref<Category | null>(null)
const open = ref(false)
const form = reactive({ name: '', slug: '' })
const saving = ref(false)
const deleting = ref(false)
const confirmingDelete = ref(false)
const error = ref<string | null>(null)

function edit(c: Category | null) {
  editing.value = c
  Object.assign(form, { name: c?.name ?? '', slug: c?.slug ?? '' })
  error.value = null
  confirmingDelete.value = false
  open.value = true
}

async function save() {
  if (!form.name.trim()) return
  saving.value = true
  error.value = null
  const body = { name: form.name.trim(), slug: form.slug.trim() || null }
  try {
    if (editing.value) await adminFetch(`/admin/categories/${editing.value.id}`, { method: 'PUT', body })
    else await adminFetch('/admin/categories', { method: 'POST', body })
    toast.success(editing.value ? 'Category saved' : 'Category added', form.name)
    open.value = false
    await refresh()
  } catch (err) {
    error.value = failureMessage(err)
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!editing.value) return
  deleting.value = true
  error.value = null
  try {
    await adminFetch(`/admin/categories/${editing.value.id}`, { method: 'DELETE' })
    toast.success('Category deleted', editing.value.name)
    open.value = false
    await refresh()
  } catch (err) {
    error.value = failureMessage(err, 'Not deleted. Try again in a moment.')
    confirmingDelete.value = false
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader title="Categories" description="How the catalogue is grouped on the storefront.">
      <template #actions>
        <UiPermissionGate capability="products.write" quiet>
          <UiButton size="sm" @click="edit(null)"><PhPlus :size="16" />New category</UiButton>
        </UiPermissionGate>
      </template>
    </UiPageHeader>

    <div v-if="pending" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="i in 5" :key="i" class="h-24 animate-pulse rounded-lg bg-bg-sunken" />
    </div>

    <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="c in categories" :key="c.id" class="card card-pad flex items-start gap-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded bg-bg-sunken text-fg-faint">
          <PhTag :size="18" />
        </span>
        <NuxtLink :to="{ path: '/products', query: { category: c.name } }" class="min-w-0 flex-1 hover:underline">
          <span class="block truncate text-ui font-medium text-fg-strong">{{ c.name }}</span>
          <span class="block truncate font-mono text-meta text-fg-faint">/{{ c.slug }}</span>
          <span v-if="c.productCount !== undefined" class="mt-1 block text-meta text-fg-muted">
            {{ c.productCount }} product{{ c.productCount === 1 ? '' : 's' }}
          </span>
        </NuxtLink>
        <button
          v-if="can('products.write')" type="button" class="toolbar-btn shrink-0"
          :aria-label="`Edit ${c.name}`" @click="edit(c)"
        >
          <PhPencilSimple :size="16" />
        </button>
      </li>
    </ul>

    <UiModal v-model:open="open" size="sm" :title="editing ? 'Edit category' : 'New category'">
      <form id="category-form" class="flex flex-col gap-4" @submit.prevent="save">
        <UiField v-model="form.name" label="Name" required />
        <UiField
          v-model="form.slug" label="Web address"
          :hint="editing ? 'Changing it changes the category’s shop links.' : 'Leave blank to make one from the name.'"
        />
        <p v-if="error" class="rounded-lg bg-danger-soft px-3 py-2.5 text-meta text-danger" role="alert">{{ error }}</p>
      </form>
      <template #footer>
        <template v-if="editing && can('products.delete')">
          <UiButton
            v-if="!confirmingDelete" variant="ghost" size="sm" class="mr-auto text-danger"
            @click="confirmingDelete = true"
          >Delete</UiButton>
          <UiButton
            v-else variant="ghost" size="sm" class="mr-auto text-danger" :loading="deleting"
            @click="remove"
          >Delete {{ editing.name }}?</UiButton>
        </template>
        <UiButton variant="secondary" size="sm" @click="open = false">Cancel</UiButton>
        <UiButton size="sm" type="submit" form="category-form" :loading="saving" :disabled="!form.name.trim()">
          {{ editing ? 'Save' : 'Add category' }}
        </UiButton>
      </template>
    </UiModal>
  </div>
</template>
