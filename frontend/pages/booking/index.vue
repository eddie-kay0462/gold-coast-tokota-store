<script setup lang="ts">
const activeTab = ref<'workshop' | 'diy'>('workshop')

useSeoMeta({
  title: 'Book a Workshop or DIY Order — Gold Coast Tokota',
  description: 'Book an in-person sandal-making workshop or submit a custom DIY sandal order.',
})
</script>

<template>
  <div class="page-gutter section-y mx-auto w-full max-w-[calc(56rem+120px)]">
    <div class="flex gap-4 border-b">
      <button type="button" :class="activeTab === 'workshop' ? 'font-semibold' : ''" @click="activeTab = 'workshop'">
        Workshop
      </button>
      <button type="button" :class="activeTab === 'diy' ? 'font-semibold' : ''" @click="activeTab = 'diy'">
        DIY Sandals
      </button>
    </div>
    <BookingWorkshopBookingForm v-if="activeTab === 'workshop'" />
    <BookingDiyOrderForm v-else />
  </div>
</template>
