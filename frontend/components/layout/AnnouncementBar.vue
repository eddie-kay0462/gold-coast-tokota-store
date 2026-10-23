<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'
import { countryName, flagUrl } from '~/utils/geo'
import { whatsappMessage } from '~/utils/whatsapp'

/**
 * The announcement strip above the header: the whole black band, one line.
 *
 * Restyled 23 Oct to sit with the rest of the site: pure black (`ink`, #000),
 * pure white type at weight 400 in the header nav's tracked capitals, and
 * square controls. It used to be two lines of light-weight (300) type at
 * 10 and 12px, which anti-aliasing turned grey on black, in mixed case on a
 * phone, beside a rounded currency pill with a grey edge.
 *
 * - Desktop: support hours on the left, the rotating message centred, and
 *   WhatsApp, flag and currency on the right. The middle is centred against
 *   the bar, the same three-column grid the logo row uses.
 * - Phone: the messages and the hours scroll in one marquee beside the flag
 *   and currency. A centred line has no room to hold still on a 320px phone.
 *
 * The messages are a short list cross-fading in place on a slow loop, as in
 * the approved Template B mockup.
 *
 * Copy comes from the admin-editable `SiteSetting.announcements` because these
 * are commercial claims (delivery terms, accepted payment methods) that the
 * brand has to be able to correct without a deploy. The fallback below is the
 * design fallback pattern used everywhere else in this app: what renders before
 * the API answers, and it is deliberately limited to claims the rest of the
 * project can stand behind.
 *
 * The mockup's own copy included "Free delivery in Accra" and "Order online,
 * pick up in Osu". Neither ships until the brand confirms them; checkout
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
 * published hours (Monday–Saturday, 9:00–17:00 GMT) as the design fallback,
 * the same pattern the rotating line above uses. It moved out of a constant
 * once the admin app turned out to have had a field for it all along.
 */
const FALLBACK_HOURS = 'Mon–Sat · 9am–5pm GMT'

const siteSettings = useSiteSettingsStore()
// The flag shows where the visitor is connecting from (resolved server-side,
// refined with browser signals a VPN doesn't change).
const { geo } = useVisitorCountry()

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
  <!-- Dark, so it carries `chrome-dark` for a white focus ring. -->
  <div class="chrome-dark w-full bg-ink text-white">
    <div class="flex h-10 w-full items-center gap-3 px-5 sm:grid sm:grid-cols-[1fr_auto_1fr] lg:px-[30px]">
      <!-- Left: when someone can expect a reply. From md; on a phone it rides
           in the marquee instead. -->
      <p class="bar-type hidden min-w-0 truncate sm:block">
        <span class="hidden md:inline">{{ supportHours }}</span>
      </p>

      <!-- Phone: one scrolling line of every message plus the hours. -->
      <div class="bar-type bar-fade flex min-w-0 flex-1 items-center sm:hidden">
        <CommonMarquee :copies="2" :duration="26">
          <template v-for="message in [...messages, supportHours]" :key="message">
            <span class="whitespace-nowrap px-3">{{ message }}</span>
            <span aria-hidden="true">·</span>
          </template>
        </CommonMarquee>
      </div>

      <!-- From sm: one message at a time, cross-fading in place. A grid, not
           absolute positioning, so the line keeps its height mid-fade. -->
      <div class="hidden justify-center sm:flex">
        <p class="sr-only">{{ messages.join('. ') }}.</p>
        <span class="grid" aria-hidden="true">
          <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-500"
            leave-active-class="motion-safe:transition motion-safe:duration-500"
            enter-from-class="opacity-0 motion-safe:translate-y-1"
            leave-to-class="opacity-0 motion-safe:-translate-y-1"
          >
            <span :key="index" class="bar-type col-start-1 row-start-1 whitespace-nowrap text-center">
              {{ messages[index] }}
            </span>
          </Transition>
        </span>
      </div>

      <!-- Right: WhatsApp (from lg; the floating button covers smaller
           screens), the visitor's flag, and the currency switch. -->
      <div class="flex shrink-0 items-center justify-end gap-4">
        <CommonWhatsAppLink
          source="announcement"
          variant="quiet"
          :message="whatsappMessage.general()"
          class="bar-type hidden !text-white decoration-white/60 hover:decoration-white lg:inline-flex"
        >
          Message us on WhatsApp
        </CommonWhatsAppLink>
        <span class="flex items-center gap-1">
          <img
            :key="geo.country"
            :src="flagUrl(geo.country)"
            :alt="countryName(geo.country)"
            :title="countryName(geo.country)"
            class="h-3 w-[17px] shrink-0 object-cover"
            width="17"
            height="12"
          >
          <CommonCurrencyToggle tone="dark" />
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* One type treatment for the whole bar: the header nav's tracked capitals,
   at 400 so the strokes stay solid white on black. */
.bar-type {
  font-size: 11px;
  line-height: 1;
  font-weight: 400;
  letter-spacing: 1.4px;
  text-transform: uppercase;
  color: #fff;
}

/* The marquee fades out at both edges instead of being cut off. */
.bar-fade {
  mask-image: linear-gradient(to right, transparent, #000 14px, #000 calc(100% - 14px), transparent);
}
</style>
