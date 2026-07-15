<script setup lang="ts">
defineProps<{
  label: string
  name: string
  type?: string
  required?: boolean
  error?: string
}>()
const model = defineModel<string>()
</script>

<template>
  <div>
    <label :for="name" class="mb-1 block text-sm font-medium">{{ label }}</label>
    <input
      :id="name"
      v-model="model"
      :name="name"
      :type="type || 'text'"
      :required="required"
      class="w-full rounded border px-3 py-2"
      :aria-invalid="!!error"
      :aria-describedby="error ? `${name}-error` : undefined"
    >
    <p v-if="error" :id="`${name}-error`" class="mt-1 text-sm text-red-600">{{ error }}</p>
  </div>
</template>
