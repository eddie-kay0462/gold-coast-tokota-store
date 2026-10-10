<script setup lang="ts">
/**
 * Shown when the selected workshop session is full.
 *
 * This used to emit a `joinWaitlist` event that nothing listened for, so the
 * button did nothing at all. It is a plain explanatory panel now: the backend
 * already handles the waitlist itself: `BookingController` locks the session
 * row and files the booking as `waitlisted` when capacity is gone, so joining
 * the waitlist *is* submitting the form, and a second button implying a
 * separate action was the wrong model.
 */
defineProps<{ label?: string }>()
</script>

<template>
  <!-- Light, outlined in ink like the rest of the booking page. It used to sit
       on the dark `chrome` ground with a gold label, from before the home page
       went white. -->
  <div class="flex w-full flex-col gap-1 border border-ink bg-white px-4 py-3.5">
    <p class="caps-label text-ink">Waitlist</p>
    <p class="text-label text-graphite">
      {{ label ?? 'This session is full. Send your details anyway and we’ll hold your place on the waitlist. We’ll be in touch as soon as a spot opens.' }}
    </p>
  </div>
</template>
