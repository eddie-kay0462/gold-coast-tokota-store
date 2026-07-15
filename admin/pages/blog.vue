<script setup lang="ts">
const body = ref('')
</script>

<template>
  <div>
    <h1 class="text-xl font-semibold">Blog</h1>
    <PageEditor v-model="body" class="mt-4" />
  </div>
</template>
