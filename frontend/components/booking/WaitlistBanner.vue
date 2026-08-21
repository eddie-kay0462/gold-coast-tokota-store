<script setup lang="ts">
defineProps<{ sessionId: string }>()
const emit = defineEmits<{ joinWaitlist: [sessionId: string] }>()
</script>

<template>
  <div class="min-w-0 rounded bg-amber-50 px-3 py-2 text-caption text-amber-800">
    Session full —
    <button type="button" class="underline" @click="emit('joinWaitlist', sessionId)">join waitlist</button>
  </div>
</template>
