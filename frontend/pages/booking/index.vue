<script setup lang="ts">
const activeTab = ref<'workshop' | 'diy'>('workshop')

useSeoMeta({
  title: 'Book a Workshop or DIY Order — Gold Coast Tokota',
  description: 'Book an in-person sandal-making workshop or submit a custom DIY sandal order.',
})

const { href: whatsappHref } = useWhatsApp(
  () => (activeTab.value === 'workshop'
    ? "Hi Gold Coast Tokota, I'd like to arrange a workshop booking."
    : "Hi Gold Coast Tokota, I'd like to arrange a custom DIY sandal order."),
)
</script>

<template>
  <div class="page-gutter section-y mx-auto w-full max-w-[calc(64rem+120px)]">
    <!-- Centred masthead, from the approved Template B mockup. -->
    <div class="mb-10 text-center">
      <p class="text-eyebrow uppercase tracking-[0.6px] text-muted">Bookings</p>
      <h1 class="mt-2 text-display-section font-light text-black">Spend time in the workshop</h1>
    </div>

    <!-- Tabs had no padding at all, so the tap target was the ~19px text box. -->
    <div class="flex justify-center gap-2 border-b border-line" role="tablist">
      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'workshop'"
        class="flex min-h-[44px] items-center px-6 text-label uppercase"
        :class="activeTab === 'workshop' ? 'border-b-2 border-graphite font-normal text-black' : 'text-muted'"
        @click="activeTab = 'workshop'"
      >
        Workshop
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'diy'"
        class="flex min-h-[44px] items-center px-6 text-label uppercase"
        :class="activeTab === 'diy' ? 'border-b-2 border-graphite font-normal text-black' : 'text-muted'"
        @click="activeTab = 'diy'"
      >
        DIY Sandals
      </button>
    </div>

    <BookingWorkshopBookingForm v-if="activeTab === 'workshop'" />
    <BookingDiyOrderForm v-else />

    <p v-if="whatsappHref" class="mt-8 text-center">
      <a
        :href="whatsappHref"
        target="_blank"
        rel="noopener noreferrer"
        class="-my-3 inline-flex min-h-[44px] items-center py-3 text-caption text-gold-deep underline"
      >
        Prefer to arrange over WhatsApp?
      </a>
    </p>
  </div>
</template>
