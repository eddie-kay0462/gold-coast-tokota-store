<script setup lang="ts">
/**
 * Delivery method selection.
 *
 * The provider is derived from the shipping country exactly as README Feature 5
 * routes it — Ghana to Yango, everywhere else to DHL. Costs shown here are
 * ESTIMATES and labelled as such: the real figure comes from the provider quote
 * at checkout-session creation, which is not built yet, and quietly presenting
 * a guess as a price is how people end up surprised at the payment screen.
 */
const props = defineProps<{ country: string }>()
const method = defineModel<'standard' | 'express'>({ default: 'standard' })

const isDomestic = computed(() => props.country === 'GH')
const provider = computed(() => (isDomestic.value ? 'Yango' : 'DHL'))

const options = computed(() =>
  isDomestic.value
    ? [
        { id: 'standard' as const, label: 'Standard', eta: '2–4 working days', note: 'Free on orders over ₵1,500' },
        { id: 'express' as const, label: 'Express', eta: '1–2 working days', note: 'Accra and Tema only' },
      ]
    : [
        { id: 'standard' as const, label: 'Standard international', eta: '7–14 working days', note: 'Tracked' },
        { id: 'express' as const, label: 'Express international', eta: '3–5 working days', note: 'Tracked and signed for' },
      ],
)
</script>

<template>
  <fieldset class="flex w-full flex-col items-start gap-3">
    <legend class="mb-2 text-caption font-normal text-graphite">
      Delivery method — via {{ provider }}
    </legend>

    <label
      v-for="option in options"
      :key="option.id"
      class="flex w-full min-w-0 cursor-pointer items-start gap-3 border p-4"
      :class="method === option.id ? 'border-graphite' : 'border-line'"
    >
      <input
        v-model="method"
        type="radio"
        name="delivery-method"
        :value="option.id"
        class="mt-1 size-4 shrink-0 accent-graphite"
      >
      <span class="flex min-w-0 flex-1 flex-col gap-0.5">
        <span class="text-body text-black">{{ option.label }}</span>
        <span class="text-caption text-muted">{{ option.eta }} · {{ option.note }}</span>
      </span>
    </label>

    <p class="w-full text-caption text-muted">
      Delivery times and costs are estimates. The exact charge is confirmed by
      {{ provider }} when your order is placed.
    </p>
  </fieldset>
</template>
