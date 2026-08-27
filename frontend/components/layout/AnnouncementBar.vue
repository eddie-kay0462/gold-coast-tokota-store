<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'
import { whatsappMessage } from '~/utils/whatsapp'

/**
 * The rotating announcement strip, transcribed from the approved Template B
 * mockup: a short list of messages cross-fading in place on a slow loop.
 *
 * Copy comes from the admin-editable `SiteSetting.announcements` because these
 * are commercial claims — delivery terms, accepted payment methods — that the
 * brand has to be able to correct without a deploy. The fallback below is the
 * design fallback pattern used everywhere else in this app: what renders before
 * the API answers, and it is deliberately limited to claims the rest of the
 * project can stand behind.
 *
 * The mockup's own copy included "Free delivery in Accra" and "Order online,
 * pick up in Osu". Neither ships until the brand confirms them — checkout
 * charges for Accra delivery, and the brand address on file is Haatso.
 */
const FALLBACK_ANNOUNCEMENTS = [
  'Handcrafted in Ghana',
  'Pay with MoMo or card',
  'We ship worldwide',
]

/** Seconds each message holds before the next one fades in. */
const ROTATE_SECONDS = 4

/**
 * The second line: when someone can expect an answer.
 *
 * Admin-editable via `SiteSetting.business_hours`, with the brand guidelines'
 * published hours (Monday–Saturday, 9:00–17:00 GMT) as the design fallback —
 * the same pattern the rotating line above uses. It moved out of a constant
 * once the admin app turned out to have had a field for it all along.
 */
const FALLBACK_HOURS = 'Mon–Sat · 9am–5pm GMT'

const siteSettings = useSiteSettingsStore()

const supportHours = computed(() => siteSettings.businessHours || FALLBACK_HOURS)

const messages = computed(() =>
  siteSettings.announcements.length ? siteSettings.announcements : FALLBACK_ANNOUNCEMENTS,
)

const index = ref(0)
let timer: ReturnType<typeof setInterval> | undefined

// Starts on mount only, so the server and the first client render agree on
// message 0 and hydration has nothing to reconcile.
onMounted(() => {
  if (messages.value.length < 2) return
  timer = setInterval(() => {
    index.value = (index.value + 1) % messages.value.length
  }, ROTATE_SECONDS * 1000)
})

onBeforeUnmount(() => clearInterval(timer))

// A message list that shrinks (an admin removing one) must not strand the
// index past the end.
watch(messages, (list) => {
  if (index.value >= list.length) index.value = 0
})
</script>

<template>
  <div class="min-w-0 flex-1 py-1.5 text-center">
    <!-- Line 1. Below `sm` the messages scroll instead of rotating: the flag and
         currency cluster leave roughly 180px on a 320px phone, which is not
         enough for a centred line to hold still in — that was a measured overlap
         bug, and the marquee is the treatment that fixed it. -->
    <div class="flex items-center text-caption text-white sm:hidden">
      <CommonMarquee :copies="2" :duration="24">
        <template v-for="message in messages" :key="message">
          <span class="whitespace-nowrap pr-2">{{ message }}</span>
          <span class="pr-2 text-gold" aria-hidden="true">&middot;</span>
        </template>
      </CommonMarquee>
    </div>

    <!-- From `sm` up: one message at a time, cross-fading in place. The stack is
         a grid rather than absolute positioning so the bar's height is set by
         the tallest message and never collapses mid-transition. -->
    <div class="hidden justify-center sm:flex">
      <p class="sr-only">{{ messages.join('. ') }}.</p>
      <span class="grid" aria-hidden="true">
        <Transition
          enter-active-class="motion-safe:transition motion-safe:duration-500"
          leave-active-class="motion-safe:transition motion-safe:duration-500"
          enter-from-class="opacity-0 motion-safe:translate-y-1.5"
          leave-to-class="opacity-0 motion-safe:-translate-y-1.5"
        >
          <span
            :key="index"
            class="col-start-1 row-start-1 text-center text-tag uppercase text-white"
          >
            {{ messages[index] }}
          </span>
        </Transition>
      </span>
    </div>

    <!-- Line 2: when someone can expect a reply, and how to get one. Quieter
         than the line above so the two read as message and footnote rather than
         as two competing announcements. -->
    <p class="mt-0.5 text-tag uppercase text-white/55">
      {{ supportHours }}
      <!-- Hidden below `sm`: the floating WhatsApp button is already on screen
           there, and a second link to the same place is noise on a 320px bar.
           `quiet` styling is overridden for the dark ground. -->
      <span class="hidden sm:inline">
        <span class="text-gold" aria-hidden="true">&nbsp;&middot;&nbsp;</span>
        <CommonWhatsAppLink
          source="announcement"
          variant="quiet"
          :message="whatsappMessage.general()"
          class="!text-white/80 decoration-white/40 hover:!text-white"
        >
          Message us on WhatsApp
        </CommonWhatsAppLink>
      </span>
    </p>
  </div>
</template>
