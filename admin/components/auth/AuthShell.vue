<script setup lang="ts">
/**
 * The frame around the signed-out pages (sign in, forgot password, choose or
 * reset a password): a rounded photo panel on the left from `lg` up, and the
 * form centred on the right. Laid out like the IRIS operations portal's
 * sign-in, with Gold Coast Tokota's own photo and wordmark.
 *
 * Inputs and buttons inside are restyled here, not in `UiField`/`UiButton`,
 * so the rest of the dashboard keeps its denser look.
 */
</script>

<template>
  <div class="flex min-h-dvh bg-bg lg:p-4">
    <!-- Photo panel. Hidden below lg, where it would push the form below the fold. -->
    <div
      class="relative hidden flex-col justify-between overflow-hidden rounded-3xl bg-ink bg-cover p-12 lg:flex lg:w-1/2"
      style="background-image: url('/brand/login-bg.jpg'); background-position: center 30%"
    >
      <div class="absolute inset-0 bg-gradient-to-b from-black/55 via-black/10 to-black/70" aria-hidden="true" />

      <div class="relative flex items-center gap-3">
        <img src="/brand/logo-white.png" alt="Gold Coast Tokota" class="h-7 w-auto object-contain">
        <span class="mt-1 text-xs font-medium uppercase tracking-[0.2em] text-white/50">Admin</span>
      </div>

      <div class="relative space-y-3">
        <div class="h-px w-10 bg-white/30" />
        <p class="text-lg font-light leading-snug text-white/90">Crafted with Purpose. Inspired by Culture.</p>
        <p class="text-xs tracking-wide text-white/50">Haatso, Accra · Ghana</p>
      </div>
    </div>

    <!-- Form -->
    <div class="flex w-full flex-col items-center justify-center px-6 py-12 sm:px-8 lg:w-1/2">
      <div class="auth-form w-full max-w-sm">
        <div class="mb-10 flex items-center gap-2 lg:hidden">
          <img src="/brand/logo.png" alt="Gold Coast Tokota" class="h-6 w-auto object-contain dark:hidden">
          <img src="/brand/logo-white.png" alt="Gold Coast Tokota" class="hidden h-6 w-auto object-contain dark:block">
          <span class="ml-1 mt-0.5 text-sm font-medium uppercase tracking-widest text-fg-faint">Admin</span>
        </div>

        <slot />

        <p class="mt-8 text-center text-xs text-fg-faint">For the Gold Coast Tokota team only.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* IRIS's fields: a soft sunken fill that lifts to the page colour on focus. */
.auth-form :deep(.field) {
  @apply rounded-lg border-border bg-bg-sunken px-4 py-2.5 transition
         focus:border-border-strong focus:bg-bg focus:outline-none focus:ring-2 focus:ring-border;
}
.auth-form :deep(.field-label) {
  @apply text-sm text-fg;
}
/* And its pill-shaped submit button. */
.auth-form :deep(button[type='submit']),
.auth-form :deep(a.auth-pill) {
  @apply rounded-full font-semibold;
}
</style>
