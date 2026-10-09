<script setup lang="ts">
import { PhCaretDown } from '@phosphor-icons/vue'

/**
 * One row of an accordion: an uppercase heading with a chevron, and a panel
 * that opens and closes smoothly.
 *
 * The animation is CSS only, so it works during SSR and needs no measuring:
 * the panel is a one-row grid whose row goes from `0fr` to `1fr`, which
 * browsers can transition, unlike `height: auto`. The content is always in the
 * DOM, so search engines read it and the page doesn't jump on hydration.
 *
 * A closed panel is `visibility: hidden` once it finishes closing, so its
 * links drop out of the Tab order and screen readers skip it. Reduced motion
 * turns the transition off.
 */
const props = withDefaults(
  defineProps<{
    title: string
    defaultOpen?: boolean
  }>(),
  { defaultOpen: false },
)

const open = ref(props.defaultOpen)
const id = useId()
</script>

<template>
  <div class="border-b border-line">
    <h3>
      <button
        :id="`${id}-trigger`"
        type="button"
        class="group flex min-h-[60px] w-full items-center justify-between gap-4 py-4 text-left text-label font-normal uppercase text-black"
        :aria-expanded="open"
        :aria-controls="`${id}-panel`"
        @click="open = !open"
      >
        {{ title }}
        <PhCaretDown
          :size="18"
          aria-hidden="true"
          class="shrink-0 text-graphite transition-transform duration-300 ease-out motion-reduce:transition-none"
          :class="open ? 'rotate-180' : ''"
        />
      </button>
    </h3>

    <div
      :id="`${id}-panel`"
      role="region"
      :aria-labelledby="`${id}-trigger`"
      class="accordion-panel"
      :class="{ 'is-open': open }"
    >
      <div class="min-h-0 overflow-hidden">
        <!-- The label size, but with ordinary letter-spacing and a looser
             line height: `text-label`'s 1.4px tracking is for short uppercase
             labels and stretches full sentences. -->
        <div class="pb-6 text-label font-light leading-[1.6] tracking-[0.2px] text-graphite">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.accordion-panel {
  display: grid;
  grid-template-rows: 0fr;
  visibility: hidden;
  /* Closing: collapse first, then hide once the collapse has finished. */
  transition:
    grid-template-rows 300ms cubic-bezier(0.4, 0, 0.2, 1),
    visibility 0s linear 300ms;
}

.accordion-panel.is-open {
  grid-template-rows: 1fr;
  visibility: visible;
  /* Opening: visible straight away so the content is there as it expands. */
  transition:
    grid-template-rows 300ms cubic-bezier(0.4, 0, 0.2, 1),
    visibility 0s;
}

@media (prefers-reduced-motion: reduce) {
  .accordion-panel,
  .accordion-panel.is-open {
    transition: none;
  }
}
</style>
