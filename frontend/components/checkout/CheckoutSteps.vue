<script setup lang="ts">
import { PhCheck } from '@phosphor-icons/vue'

export type CheckoutStep = 'details' | 'delivery' | 'payment'

const props = defineProps<{ current: CheckoutStep }>()
const emit = defineEmits<{ go: [step: CheckoutStep] }>()

const steps: { id: CheckoutStep, label: string }[] = [
  { id: 'details', label: 'Details' },
  { id: 'delivery', label: 'Delivery' },
  { id: 'payment', label: 'Payment' },
]

const currentIndex = computed(() => steps.findIndex((s) => s.id === props.current))
</script>

<template>
  <ol class="flex w-full min-w-0 items-center gap-2">
    <li v-for="(step, index) in steps" :key="step.id" class="flex min-w-0 flex-1 items-center gap-2">
      <!-- Completed steps go back; future steps are not reachable by click,
           because the data they need hasn't been entered yet. -->
      <component
        :is="index < currentIndex ? 'button' : 'div'"
        :type="index < currentIndex ? 'button' : undefined"
        class="flex min-h-[44px] min-w-0 flex-1 items-center gap-2 border-t-2 pt-2 text-left"
        :class="index <= currentIndex ? 'border-graphite' : 'border-line'"
        :aria-current="index === currentIndex ? 'step' : undefined"
        @click="index < currentIndex && emit('go', step.id)"
      >
        <span
          class="flex size-5 shrink-0 items-center justify-center rounded-full text-tag"
          :class="index <= currentIndex ? 'bg-graphite text-white' : 'bg-line text-subtle'"
        >
          <PhCheck v-if="index < currentIndex" :size="11" weight="bold" />
          <template v-else>{{ index + 1 }}</template>
        </span>
        <span
          class="truncate text-caption"
          :class="index <= currentIndex ? 'text-graphite' : 'text-muted'"
        >{{ step.label }}</span>
      </component>
    </li>
  </ol>
</template>
