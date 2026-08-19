<script setup lang="ts">
/**
 * The three line icons beside the shipping/returns/gift blurbs on the product
 * page. Each one is exported from Figma as several separate vector layers
 * rather than a single file (nodes 10:3586–10:3588), so the layers and their
 * Figma insets are reproduced here verbatim rather than redrawn.
 */
const props = defineProps<{ name: 'shipping' | 'returns' | 'gift' }>()

type Layer = { src: string, box: string, pad: string }

const ICONS: Record<string, Layer[]> = {
  shipping: [
    { src: '/design/icons/ship-a.svg', box: 'inset-[12.5%_33.33%_33.33%_4.17%]', pad: 'inset-[-5.77%_-5%]' },
    { src: '/design/icons/ship-b.svg', box: 'inset-[33.33%_4.17%_33.33%_66.67%]', pad: 'inset-[-9.38%_-10.71%]' },
    { src: '/design/icons/ship-wheel.svg', box: 'inset-[66.67%_66.67%_12.5%_12.5%]', pad: 'inset-[-15%]' },
    { src: '/design/icons/ship-wheel.svg', box: 'inset-[66.67%_12.5%_12.5%_66.67%]', pad: 'inset-[-15%]' },
  ],
  returns: [
    { src: '/design/icons/return-a.svg', box: 'inset-[17.54%_31.25%_60.83%_31.25%]', pad: 'inset-[-14.45%_-8.33%]' },
    { src: '/design/icons/return-b.svg', box: 'inset-[8.34%_12.5%]', pad: 'inset-[-3.75%_-4.17%]' },
    { src: '/design/icons/return-c.svg', box: 'inset-[29%_13.63%_49.96%_13.62%]', pad: 'inset-[-14.85%_-4.3%]' },
    { src: '/design/icons/return-d.svg', box: 'bottom-[8%] left-1/2 right-1/2 top-1/2', pad: 'inset-[-7.44%_-0.75px]' },
  ],
  gift: [
    { src: '/design/icons/gift-a.svg', box: 'bottom-[8.33%] left-[16.67%] right-[16.67%] top-1/2', pad: 'inset-[-7.5%_-4.69%]' },
    { src: '/design/icons/gift-b.svg', box: 'bottom-1/2 left-[8.33%] right-[8.33%] top-[29.17%]', pad: 'inset-[-15%_-3.75%]' },
    { src: '/design/icons/gift-c.svg', box: 'bottom-[8.33%] left-1/2 right-1/2 top-[29.17%]', pad: 'inset-[-5%_-0.75px]' },
    { src: '/design/icons/gift-d.svg', box: 'bottom-[70.83%] left-[20.83%] right-1/2 top-[8.33%]', pad: 'inset-[-15%_-10.71%]' },
    { src: '/design/icons/gift-e.svg', box: 'bottom-[70.83%] left-1/2 right-[20.83%] top-[8.33%]', pad: 'inset-[-15%_-10.71%]' },
  ],
}

const layers = computed(() => ICONS[props.name] ?? [])
</script>

<template>
  <div class="relative size-[34px] shrink-0 overflow-clip" aria-hidden="true">
    <div v-for="(layer, index) in layers" :key="index" class="absolute" :class="layer.box">
      <div class="absolute" :class="layer.pad">
        <img :src="layer.src" alt="" class="block size-full max-w-none">
      </div>
    </div>
  </div>
</template>
