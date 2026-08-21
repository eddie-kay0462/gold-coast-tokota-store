<script setup lang="ts">
const activeTab = ref<'workshop' | 'diy'>('workshop')

useSeoMeta({
  title: 'Book a Workshop or DIY Order — Gold Coast Tokota',
  description: 'Book an in-person sandal-making workshop or submit a custom DIY sandal order.',
})
</script>

<template>
  <div class="page-gutter section-y mx-auto w-full max-w-[calc(56rem+120px)]">
    <!-- Tabs had no padding at all, so the tap target was the ~19px text box. -->
    <div class="flex gap-2 border-b border-line" role="tablist">
      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'workshop'"
        class="flex min-h-[44px] items-center px-4 text-label"
        :class="activeTab === 'workshop' ? 'border-b-2 border-graphite font-normal text-black' : 'text-muted'"
        @click="activeTab = 'workshop'"
      >
        Workshop
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'diy'"
        class="flex min-h-[44px] items-center px-4 text-label"
        :class="activeTab === 'diy' ? 'border-b-2 border-graphite font-normal text-black' : 'text-muted'"
        @click="activeTab = 'diy'"
      >
        DIY Sandals
      </button>
    </div>
    <BookingWorkshopBookingForm v-if="activeTab === 'workshop'" />
    <BookingDiyOrderForm v-else />
  </div>
</template>
