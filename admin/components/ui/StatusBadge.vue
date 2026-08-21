<script setup lang="ts">
import { PhCheckCircle, PhClock, PhWarningCircle, PhXCircle, PhTruck, PhPackage, PhHourglass } from '@phosphor-icons/vue'
import { humanise } from '~/utils/formatters'

/**
 * Status pill for orders, bookings, returns, shipments and templates.
 *
 * The old scaffold version hardcoded stock Tailwind (`bg-green-100
 * text-green-700`), which meant it was the one component that ignored the
 * brand palette and would have stayed light-mode-only. Tones now resolve to
 * the semantic tokens, so dark mode needs nothing extra.
 */
const props = defineProps<{ status: string; showIcon?: boolean }>()

type Tone = 'neutral' | 'accent' | 'success' | 'warning' | 'danger' | 'info'

const map: Record<string, { tone: Tone; icon: unknown; label?: string }> = {
  // Orders
  pending: { tone: 'warning', icon: PhClock },
  paid: { tone: 'success', icon: PhCheckCircle },
  processing: { tone: 'info', icon: PhPackage },
  shipped: { tone: 'info', icon: PhTruck },
  delivered: { tone: 'success', icon: PhCheckCircle },
  cancelled: { tone: 'neutral', icon: PhXCircle },
  refunded: { tone: 'neutral', icon: PhXCircle },
  inventory_conflict: { tone: 'danger', icon: PhWarningCircle, label: 'Stock conflict' },
  // Bookings
  confirmed: { tone: 'success', icon: PhCheckCircle },
  waitlisted: { tone: 'accent', icon: PhHourglass },
  completed: { tone: 'neutral', icon: PhCheckCircle },
  // Returns
  requested: { tone: 'warning', icon: PhClock },
  approved: { tone: 'success', icon: PhCheckCircle },
  rejected: { tone: 'danger', icon: PhXCircle },
  received: { tone: 'info', icon: PhPackage },
  exchanged: { tone: 'success', icon: PhCheckCircle },
  // Shipments
  awaiting_pickup: { tone: 'warning', icon: PhClock, label: 'Awaiting pickup' },
  in_transit: { tone: 'info', icon: PhTruck, label: 'In transit' },
  customs: { tone: 'warning', icon: PhHourglass },
  out_for_delivery: { tone: 'info', icon: PhTruck, label: 'Out for delivery' },
  exception: { tone: 'danger', icon: PhWarningCircle },
}

const entry = computed(() => map[props.status] ?? { tone: 'neutral' as Tone, icon: PhClock })
const label = computed(() => entry.value.label ?? humanise(props.status))
</script>

<template>
  <UiBadge :tone="entry.tone" :dot="!showIcon">
    <component :is="entry.icon" v-if="showIcon" :size="12" weight="bold" />
    {{ label }}
  </UiBadge>
</template>
