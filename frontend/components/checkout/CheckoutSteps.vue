<script setup lang="ts">
import { PhCaretRight } from '@phosphor-icons/vue'

export type CheckoutStep = 'details' | 'delivery' | 'payment'

const props = defineProps<{ current: CheckoutStep }>()
const emit = defineEmits<{ go: [step: CheckoutStep], cart: [] }>()

/**
 * The Shopify checkout breadcrumb: `Cart › Information › Shipping › Payment`,
 * small and grey, with the step you are on in solid ink.
 *
 * It replaces a three-segment progress bar with numbered pills. Same rules
 * underneath: a completed step is a link back, a future step is inert text,
 * because the data it needs has not been entered yet.
 *
 * "Cart" opens the cart drawer rather than navigating — this app has no cart
 * page, the cart is a drawer.
 */
const steps: { id: CheckoutStep, label: string }[] = [
  { id: 'details', label: 'Information' },
  { id: 'delivery', label: 'Shipping' },
  { id: 'payment', label: 'Payment' },
]

const currentIndex = computed(() => steps.findIndex((s) => s.id === props.current))
</script>

<template>
  <nav aria-label="Checkout progress" class="w-full">
    <ol class="flex flex-wrap items-center gap-x-1 text-caption">
      <li class="flex items-center gap-x-1">
        <button
          type="button"
          class="-my-2.5 flex min-h-[44px] items-center py-2.5 text-muted underline hover:text-graphite"
          @click="emit('cart')"
        >
          Cart
        </button>
        <PhCaretRight :size="11" class="shrink-0 text-line" aria-hidden="true" />
      </li>

      <li v-for="(step, index) in steps" :key="step.id" class="flex items-center gap-x-1">
        <component
          :is="index < currentIndex ? 'button' : 'span'"
          :type="index < currentIndex ? 'button' : undefined"
          class="-my-2.5 flex min-h-[44px] items-center py-2.5"
          :class="index < currentIndex
            ? 'text-muted underline hover:text-graphite'
            : index === currentIndex ? 'text-black' : 'text-line'"
          :aria-current="index === currentIndex ? 'step' : undefined"
          @click="index < currentIndex && emit('go', step.id)"
        >
          {{ step.label }}
        </component>
        <PhCaretRight
          v-if="index < steps.length - 1"
          :size="11"
          class="shrink-0 text-line"
          aria-hidden="true"
        />
      </li>
    </ol>
  </nav>
</template>
