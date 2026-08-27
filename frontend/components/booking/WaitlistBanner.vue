<script setup lang="ts">
/**
 * Shown when the selected workshop session is full.
 *
 * This used to emit a `joinWaitlist` event that nothing listened for, so the
 * button did nothing at all. It is a plain explanatory panel now: the backend
 * already handles the waitlist itself — `BookingController` locks the session
 * row and files the booking as `waitlisted` when capacity is gone — so joining
 * the waitlist *is* submitting the form, and a second button implying a
 * separate action was the wrong model.
 */
defineProps<{ label?: string }>()
</script>

<template>
  <div class="flex w-full flex-col gap-1 bg-chrome px-4 py-3.5 text-white">
    <p class="text-caption font-normal uppercase tracking-[1px] text-gold">Waitlist</p>
    <p class="text-label text-white/80">
      {{ label ?? 'This session is full. Send your details anyway and we will hold your place on the waitlist — we will be in touch the moment a spot opens.' }}
    </p>
  </div>
</template>
