<script setup lang="ts">
import { SIZE_GUIDE_ROWS } from '~/utils/sizeGuide'

/**
 * `dense`: shorter rows and smaller text, for the size guide modal. Headings
 * and figures share one size either way.
 */
const props = defineProps<{ dense?: boolean }>()

const cellY = computed(() => (props.dense ? 'py-2' : 'py-3'))
const bodyText = computed(() => (props.dense ? 'text-label' : 'text-body'))
</script>

<template>
  <!-- Four short columns fit a 320px phone once the cell padding tightens
       below `sm`. The table used to have a 420px minimum width, which in the
       size guide modal hid the US column behind a sideways scroll.
       Headings may wrap ("Foot length" does on a 320px phone); the figures
       don't. `overflow-x-auto` stays as a safety net for very large text
       settings. -->
  <div class="w-full overflow-x-auto border border-line">
    <table class="w-full border-collapse text-left">
      <caption class="sr-only">Foot length in centimetres against EU, UK and US sizes</caption>
      <thead>
        <tr class="border-b border-line bg-surface">
          <th
            v-for="heading in ['Foot length', 'EU', 'UK', 'US']"
            :key="heading"
            scope="col"
            class="px-2.5 font-normal text-graphite sm:px-4"
            :class="[cellY, bodyText]"
          >
            {{ heading }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in SIZE_GUIDE_ROWS" :key="row.eu" class="border-b border-line last:border-b-0">
          <th scope="row" class="whitespace-nowrap px-2.5 font-light text-black sm:px-4" :class="[cellY, bodyText]">{{ row.cm }} cm</th>
          <td v-for="(value, column) in [row.eu, row.uk, row.us]" :key="column" class="px-2.5 text-graphite sm:px-4" :class="[cellY, bodyText]">{{ value }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
