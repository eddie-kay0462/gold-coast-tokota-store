<script setup lang="ts">
// Static per the design. The SiteSetting resource has no address field, so the
// Haatso address stays in code until one is added to the CMS schema.
const valueProps = [
  {
    id: 'shipping',
    icon: '/design/value-shipping.png',
    heading: 'Fast & Reliable Shipping',
    lines: ['Enjoy shipping and order fulfilment within 2 days of purchase.'],
  },
  {
    id: 'crafted',
    icon: '/design/value-crafted.png',
    heading: 'Consciously Crafted',
    lines: ['Designed with you and the planet in mind.'],
  },
  {
    id: 'visit',
    icon: '/design/value-visit.png',
    heading: 'Come Say Hi',
    lines: ['Visit us at', 'Gold Coast Tokota, E A Amartei, Haatso'],
  },
]
</script>

<template>
  <!-- `items-stretch` while stacked: in a `flex-col` the cross axis is horizontal,
       so `items-start` sized each block to its own content and left-aligned it —
       the three icons landed at different x positions on a phone. Stretching
       makes each block full width, and its own `items-center` then centres the
       icon and copy on the page. From `sm` the row is horizontal, where
       `items-start` correctly means top-aligned columns. -->
  <section class="page-gutter section-y mx-auto flex w-full max-w-[1406px] flex-col items-stretch gap-8 sm:flex-row sm:items-start sm:gap-4 lg:gap-1.5">
    <div
      v-for="item in valueProps"
      :key="item.id"
      class="flex min-w-0 flex-1 flex-col items-center gap-5 lg:px-[55px]"
    >
      <img :src="item.icon" alt="" aria-hidden="true" class="size-[78px] object-contain" loading="lazy">
      <div class="flex w-full flex-col items-center gap-1 text-center text-graphite">
        <h3 class="w-full text-action font-normal">{{ item.heading }}</h3>
        <p class="w-full text-label">
          <template v-for="(line, index) in item.lines" :key="index">
            {{ line }}<br v-if="index < item.lines.length - 1">
          </template>
        </p>
      </div>
    </div>
  </section>
</template>
