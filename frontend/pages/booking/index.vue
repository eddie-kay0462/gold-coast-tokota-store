<script setup lang="ts">
import { whatsappMessage } from '~/utils/whatsapp'
const activeTab = ref<'workshop' | 'diy'>('workshop')

useSeoMeta({
  title: 'Book a Workshop or DIY Order | Gold Coast Tokota',
  description: 'Book an in-person sandal-making workshop or submit a custom DIY sandal order.',
})

/**
 * Three of the six experiences in the brand guidelines are "by appointment";
 * they have no fixed day or slot, so they will never appear as a
 * `WorkshopSession` and cannot be booked from the list above. WhatsApp is their
 * only route, and without this they had no route at all.
 */
const APPOINTMENT_EXPERIENCES = [
  { name: 'Corporate Team Building Workshop', detail: 'Half or full day · up to 30 people' },
  { name: 'Cultural Craft Experience', detail: '2 hours · up to 15 people' },
  { name: 'International Visitor Experience', detail: '2–4 hours · up to 20 people' },
]
</script>

<template>
  <div class="page-gutter section-y mx-auto w-full max-w-[calc(64rem+120px)]">
    <!-- Centred masthead, from the approved Template B mockup. -->
    <div class="mb-10 text-center">
      <p class="caps-label text-muted">Bookings</p>
      <h1 class="mt-2 text-display-section font-light text-ink">Spend time in the workshop</h1>
    </div>

    <!-- Tabs had no padding at all, so the tap target was the ~19px text box. -->
    <div class="flex justify-center gap-2 border-b border-line" role="tablist">
      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'workshop'"
        class="caps-label -mb-px flex min-h-[44px] items-center border-b px-6 transition-colors"
        :class="activeTab === 'workshop' ? 'border-ink text-ink' : 'border-transparent text-subtle hover:text-ink'"
        @click="activeTab = 'workshop'"
      >
        Workshop
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'diy'"
        class="caps-label -mb-px flex min-h-[44px] items-center border-b px-6 transition-colors"
        :class="activeTab === 'diy' ? 'border-ink text-ink' : 'border-transparent text-subtle hover:text-ink'"
        @click="activeTab = 'diy'"
      >
        DIY Sandals
      </button>
    </div>

    <BookingWorkshopBookingForm v-if="activeTab === 'workshop'" />
    <BookingDiyOrderForm v-else />

    <p class="mt-8 text-center">
      <CommonWhatsAppLink
        source="booking"
        variant="quiet"
        :message="activeTab === 'workshop' ? whatsappMessage.workshop() : whatsappMessage.diyOrder()"
      >
        Prefer to arrange over WhatsApp?
      </CommonWhatsAppLink>
    </p>

    <!-- By appointment. Not bookable above by design; these have no fixed
         session, so the brand arranges each one directly. -->
    <section class="mt-14 border-t border-line pt-10">
      <h2 class="text-display-sm text-ink">By appointment</h2>
      <p class="mt-2 max-w-[560px] text-label text-subtle">
        These run on request rather than on a schedule, so we arrange them with you
        directly.
      </p>

      <ul class="mt-8 grid gap-x-6 gap-y-8 md:grid-cols-3">
        <li
          v-for="experience in APPOINTMENT_EXPERIENCES"
          :key="experience.name"
          class="flex flex-col items-start gap-3 border-t border-line pt-5"
        >
          <div class="flex flex-1 flex-col items-start gap-1">
            <h3 class="caps-title">{{ experience.name }}</h3>
            <p class="text-label tracking-[0.4px] text-subtle">{{ experience.detail }}</p>
          </div>
          <CommonWhatsAppLink
            source="booking-appointment"
            variant="quiet"
            :message="whatsappMessage.workshop(experience.name)"
          >
            Enquire on WhatsApp
          </CommonWhatsAppLink>
        </li>
      </ul>
    </section>
  </div>
</template>
