<script setup lang="ts">
import { whatsappMessage } from '~/utils/whatsapp'
export type WorkshopSession = {
  id: number | string
  scheduled_date: string | null
  scheduled_slot: string | null
  capacity: number
  remaining_capacity: number
  location_notes?: string | null
}

/**
 * The workshop session list from the approved Template B mockup: selectable
 * cards, each with a capacity chip on the right — green "N spots left", red
 * "Full".
 *
 * Replaces `BookingCalendar.vue`, which was named for a calendar it never had
 * and typed against a `booked_count` field the API does not return (it returns
 * `remaining_capacity`), so capacity would have rendered `NaN` the moment real
 * sessions arrived.
 */
defineProps<{
  sessions: WorkshopSession[]
  pending?: boolean
  modelValue?: string | null
}>()

const emit = defineEmits<{ 'update:modelValue': [string] }>()

/** "Sat 8 Aug" — the short form the mockup uses on each card. */
function formatDate(value: string | null) {
  if (!value) return 'Date to be confirmed'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })
}
</script>

<template>
  <div class="flex w-full flex-col gap-3">
    <h3 class="text-eyebrow uppercase tracking-[0.6px] text-muted">Choose a session</h3>

    <ul v-if="pending" class="flex flex-col gap-3" aria-hidden="true">
      <li v-for="n in 3" :key="n" class="h-[74px] w-full animate-pulse bg-surface" />
    </ul>

    <ul v-else-if="sessions.length" class="flex flex-col gap-3">
      <li v-for="session in sessions" :key="session.id">
        <button
          type="button"
          class="flex w-full items-center justify-between gap-4 border px-4 py-3.5 text-left transition-colors"
          :class="String(session.id) === modelValue
            ? 'border-graphite bg-surface'
            : 'border-line bg-white hover:border-graphite'"
          :aria-pressed="String(session.id) === modelValue"
          @click="emit('update:modelValue', String(session.id))"
        >
          <span class="min-w-0">
            <span class="block text-body text-black">{{ formatDate(session.scheduled_date) }}</span>
            <span class="block text-caption text-muted">{{ session.scheduled_slot }}</span>
            <span v-if="session.location_notes" class="mt-0.5 block text-caption text-muted">
              {{ session.location_notes }}
            </span>
          </span>

          <span
            class="shrink-0 whitespace-nowrap text-caption"
            :class="session.remaining_capacity > 0 ? 'text-green-700' : 'text-sale'"
          >
            {{ session.remaining_capacity > 0
              ? `${session.remaining_capacity} ${session.remaining_capacity === 1 ? 'spot' : 'spots'} left`
              : 'Full' }}
          </span>
        </button>
      </li>
    </ul>

    <CommonInlineNotice v-else title="No sessions are open right now">
      New workshop dates go up regularly. Ask us and we will let you know as soon
      as the next one opens.
      <template #action>
        <CommonWhatsAppLink
          source="booking-waitlist"
          variant="quiet"
          :message="whatsappMessage.workshop()"
        >
          Ask about the next session
        </CommonWhatsAppLink>
      </template>
    </CommonInlineNotice>
  </div>
</template>
