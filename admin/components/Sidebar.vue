<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <aside class="w-60 shrink-0 border-r p-4">
    <nav class="flex flex-col gap-2">
      <NuxtLink to="/">Dashboard</NuxtLink>
      <NuxtLink to="/orders">Orders</NuxtLink>
      <NuxtLink to="/inventory">Inventory</NuxtLink>
      <NuxtLink to="/bookings">Bookings</NuxtLink>
      <NuxtLink to="/blog">Blog</NuxtLink>
      <NuxtLink to="/about">About Page</NuxtLink>
      <NuxtLink v-if="auth.role === 'admin'" to="/settings">Site Settings</NuxtLink>
      <NuxtLink v-if="auth.role === 'admin'" to="/products">Products</NuxtLink>
      <NuxtLink to="/newsletter">Newsletter</NuxtLink>
    </nav>
  </aside>
</template>
