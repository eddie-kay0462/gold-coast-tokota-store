<script setup lang="ts">
defineProps<{ message: string; variant?: 'success' | 'error' | 'info' }>()
</script>

<template>
  <Transition name="fade">
    <div
      class="rounded px-4 py-2 text-sm text-white"
      :class="{
        'bg-green-600': variant === 'success',
        'bg-red-600': variant === 'error',
        'bg-gray-800': !variant || variant === 'info',
      }"
    >
      {{ message }}
    </div>
  </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
