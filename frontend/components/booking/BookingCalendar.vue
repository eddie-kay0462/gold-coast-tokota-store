<script setup lang="ts">
// Shared by Workshop (capacity-limited) and DIY (unlimited) booking flows.
// Workshop mode shows remaining capacity per session; DIY mode shows an
// estimated turnaround instead of a capacity constraint.
const props = defineProps<{
  mode: 'workshop' | 'diy'
  sessions?: { id: string; scheduled_date: string; scheduled_slot: string; capacity: number; booked_count: number }[]
  diyTurnaroundEstimate?: string
}>()
const emit = defineEmits<{ selectSession: [sessionId: string] }>()
</script>

<template>
  <div>
    <template v-if="mode === 'workshop'">
      <ul>
        <li v-for="session in sessions" :key="session.id" class="flex items-center justify-between border-b py-2">
          <span>{{ session.scheduled_date }} — {{ session.scheduled_slot }}</span>
          <button
            v-if="session.booked_count < session.capacity"
            type="button"
            @click="emit('selectSession', session.id)"
          >
            {{ session.capacity - session.booked_count }} spots left
          </button>
          <BookingWaitlistBanner v-else :session-id="session.id" />
        </li>
      </ul>
    </template>
    <p v-else class="text-sm text-gray-600">
      Estimated turnaround: {{ props.diyTurnaroundEstimate || 'contact us for current estimate' }}
    </p>
  </div>
</template>
