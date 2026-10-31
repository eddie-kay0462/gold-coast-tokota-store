<script setup lang="ts">
import { PhPause, PhPlay } from '@phosphor-icons/vue'

/**
 * A live preview of the storefront's announcement bar, drawn the way
 * `frontend/components/layout/AnnouncementBar.vue` draws it: a black band in
 * tracked white capitals, support hours on the left, one line at a time in
 * the middle (cross-fading every four seconds), WhatsApp, flag and currency on
 * the right; and on a phone, everything in one scrolling line.
 *
 * It reads the lines being typed, before they're saved. With none, it shows
 * the storefront's built-in lines, which is what the bar would show too. Keep
 * the two in step if the bar's look or fallback changes.
 */
const props = defineProps<{ messages: string[]; hours?: string | null }>()

/** The storefront's own lines and hours, shown when nothing's been set. */
const FALLBACK_MESSAGES = ['Handcrafted in Ghana', 'Pay with MoMo or card', 'We ship worldwide']
const FALLBACK_HOURS = 'Mon to Sat · 9am to 5pm GMT'
const ROTATE_SECONDS = 4

// Ranges read "to", as the storefront words them (`formatHours` there).
const DAY = '(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)[a-z]*\\.?'
const TIME = '\\d{1,2}(?:[:.]\\d{2})?\\s*(?:[ap]\\.?m\\.?)?'
function formatHours(hours: string): string {
  return hours
    .replace(/\s*[–—]\s*/g, ' to ')
    .replace(new RegExp(`(${DAY})\\s*-\\s*(?=${DAY})`, 'gi'), '$1 to ')
    .replace(new RegExp(`(${TIME})\\s*-\\s*(?=\\d)`, 'gi'), '$1 to ')
}

const lines = computed(() => {
  const typed = props.messages.map((m) => m.trim()).filter(Boolean)
  return typed.length ? typed : FALLBACK_MESSAGES
})
const usingFallback = computed(() => !props.messages.some((m) => m.trim()))
const supportHours = computed(() => formatHours(props.hours?.trim() || FALLBACK_HOURS))

const index = ref(0)
const playing = ref(true)
let timer: ReturnType<typeof setInterval> | undefined
function start() {
  clearInterval(timer)
  timer = setInterval(() => {
    if (playing.value && lines.value.length > 1) index.value = (index.value + 1) % lines.value.length
  }, ROTATE_SECONDS * 1000)
}
onMounted(start)
onBeforeUnmount(() => clearInterval(timer))
watch(lines, (list) => { if (index.value >= list.length) index.value = 0 })
function show(i: number) {
  index.value = i
  start()
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <div class="flex items-center justify-between gap-3">
      <p class="text-meta text-fg-muted">
        Preview<template v-if="usingFallback">: the storefront’s built-in lines, until you add your own</template>
      </p>
      <div class="flex items-center gap-1">
        <button
          v-for="(line, i) in lines" :key="`${i}-${line}`" type="button"
          class="size-2.5 rounded-full transition-colors"
          :class="i === index ? 'bg-fg-strong' : 'bg-border-strong hover:bg-fg-muted'"
          :aria-label="`Show line ${i + 1}`" @click="show(i)"
        />
        <button
          type="button" class="toolbar-btn ml-1 !size-7"
          :aria-label="playing ? 'Pause the preview' : 'Play the preview'" @click="playing = !playing"
        >
          <component :is="playing ? PhPause : PhPlay" :size="14" />
        </button>
      </div>
    </div>

    <!-- Desktop: hours · one line at a time · WhatsApp, flag, currency -->
    <div class="overflow-hidden rounded-lg border border-border" aria-label="Announcement bar, desktop" role="img">
      <div class="grid h-10 grid-cols-[1fr_auto_1fr] items-center gap-3 bg-black px-4 text-white">
        <span class="bar-type min-w-0 truncate">{{ supportHours }}</span>
        <span class="grid justify-items-center">
          <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-500"
            leave-active-class="motion-safe:transition motion-safe:duration-500"
            enter-from-class="opacity-0 motion-safe:translate-y-1"
            leave-to-class="opacity-0 motion-safe:-translate-y-1"
          >
            <span :key="index" class="bar-type col-start-1 row-start-1 whitespace-nowrap">{{ lines[index] }}</span>
          </Transition>
        </span>
        <span class="flex items-center justify-end gap-3">
          <span class="bar-type hidden whitespace-nowrap underline decoration-white/60 underline-offset-4 xl:inline">Message us on WhatsApp</span>
          <span class="flex items-center gap-1">
            <span class="h-3 w-[17px] shrink-0 bg-[linear-gradient(#ce1126_33.3%,#fcd116_33.3%_66.6%,#006b3f_66.6%)]" aria-hidden="true" />
            <span class="flex border border-white text-[10px] leading-none tracking-[1.2px]">
              <span class="bg-white px-1.5 py-1 text-black">GHS</span>
              <span class="px-1.5 py-1">USD</span>
            </span>
          </span>
        </span>
      </div>
      <!-- A sliver of the header beneath, so the bar is seen in place. -->
      <div class="flex h-6 items-center justify-center bg-white">
        <span class="text-[9px] tracking-[2px] text-neutral-400">GOLD COAST TOKOTA</span>
      </div>
    </div>

    <!-- Phone: every line, then the hours, in one scrolling marquee -->
    <div class="flex items-center gap-3">
      <div class="w-[320px] max-w-full overflow-hidden rounded-lg border border-border" aria-label="Announcement bar, phone" role="img">
        <div class="flex h-10 items-center gap-3 bg-black pl-3 pr-2 text-white">
          <div class="bar-fade min-w-0 flex-1 overflow-hidden">
            <div class="marquee flex w-max" :class="!playing && 'paused'">
              <template v-for="copy in 2" :key="copy">
                <template v-for="line in [...lines, supportHours]" :key="`${copy}-${line}`">
                  <span class="bar-type whitespace-nowrap px-3">{{ line }}</span>
                  <span class="bar-type" aria-hidden="true">·</span>
                </template>
              </template>
            </div>
          </div>
          <span class="flex border border-white text-[10px] leading-none tracking-[1.2px]">
            <span class="bg-white px-1.5 py-1 text-black">GHS</span>
            <span class="px-1.5 py-1">USD</span>
          </span>
        </div>
      </div>
      <span class="text-meta text-fg-faint">On a phone</span>
    </div>
  </div>
</template>

<style scoped>
/* The storefront bar's type: tracked capitals, 400 weight, solid white. */
.bar-type {
  font-size: 11px;
  line-height: 1;
  font-weight: 400;
  letter-spacing: 1.4px;
  text-transform: uppercase;
  color: #fff;
}
.bar-fade {
  mask-image: linear-gradient(to right, transparent, #000 14px, #000 calc(100% - 14px), transparent);
}
.marquee {
  animation: marquee 26s linear infinite;
}
.marquee.paused {
  animation-play-state: paused;
}
@keyframes marquee {
  to { transform: translateX(-50%); }
}
@media (prefers-reduced-motion: reduce) {
  .marquee { animation: none; }
}
</style>
