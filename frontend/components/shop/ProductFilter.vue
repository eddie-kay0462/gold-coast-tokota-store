<script setup lang="ts">
defineProps<{ categories: { id: string; name: string }[] }>()
const emit = defineEmits<{ select: [categoryId: string | null] }>()
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <button type="button" @click="emit('select', null)">All</button>
    <button v-for="category in categories" :key="category.id" type="button" @click="emit('select', category.id)">
      {{ category.name }}
    </button>
  </div>
</template>
