<script setup lang="ts">
const config = useRuntimeConfig()
const { data: page } = await useAsyncData('admin-about-page', () =>
  $fetch(`${config.public.apiBase}/pages/about`),
)
const body = ref((page.value as any)?.data?.body ?? '')

async function onSave() {
  await $fetch(`${config.public.apiBase}/admin/pages/about`, { method: 'PUT', body: { body: body.value } })
}
</script>

<template>
  <div>
    <h1 class="text-xl font-semibold">About Page</h1>
    <PageEditor v-model="body" class="mt-4" />
    <button type="button" class="mt-4 rounded bg-black px-4 py-2 text-white" @click="onSave">Save</button>
  </div>
</template>
