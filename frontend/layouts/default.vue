<template>
  <div class="flex min-h-screen flex-col">
    <LayoutHeader />
    <main class="flex-1">
      <slot />
    </main>
    <LayoutFooter />
    <LayoutWhatsAppButton />
    <CartDrawer />
  </div>
</template>
