<template>
  <header class="border-b">
    <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
      <NuxtLink to="/" class="font-semibold">Gold Coast Tokota</NuxtLink>
      <div class="flex items-center gap-6">
        <NuxtLink to="/shop">Shop</NuxtLink>
        <NuxtLink to="/booking">Booking</NuxtLink>
        <NuxtLink to="/blog">Stories</NuxtLink>
        <NuxtLink to="/about">About</NuxtLink>
        <CommonCurrencyToggle />
      </div>
    </nav>
  </header>
</template>
