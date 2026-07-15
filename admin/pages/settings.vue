<script setup lang="ts">
// Admin-only per two-tier role rule — server enforces this too via
// EnsureAdminRole; this client-side guard is a UX convenience, not the
// security boundary.
const auth = useAuthStore()
</script>

<template>
  <div>
    <h1 class="text-xl font-semibold">Site Settings</h1>
    <p v-if="auth.role !== 'admin'" class="mt-4 text-red-600">
      You don't have permission to view this page.
    </p>
    <SiteSettingsForm v-else class="mt-4" />
  </div>
</template>
