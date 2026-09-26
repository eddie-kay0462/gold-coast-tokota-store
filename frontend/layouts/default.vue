<template>
  <!-- Stacking order, one place so overlays can't collide:
       40 product page's phone-only sticky Add-to-Cart bar · 45 WhatsApp button ·
       50 header + mega-menu scrim · 55 Modal · 60 cart scrim · 70 cart panel.

       The WhatsApp button sits *above* the sticky bar. Both are fixed to the
       bottom of a phone viewport and at the same level they collided in the
       right-hand corner; the bar is a full-width white band, so putting it on
       top hid the button completely. The bar carries right padding instead, so
       its own button never runs under the circle.

       `min-h-dvh` rather than `min-h-screen`: on mobile Safari `100vh` is the
       URL-bar-retracted height, which makes the page taller than what is
       actually visible on first paint. -->
  <div class="flex min-h-dvh flex-col">
    <LayoutHeader />
    <!-- `min-w-0` stops a wide child from stretching the column, and the bottom
         padding reserves the corner the fixed WhatsApp button occupies. -->
    <main class="min-w-0 flex-1">
      <slot />
    </main>
    <LayoutFooter />
    <LayoutWhatsAppButton />
    <CartDrawer />
  </div>
</template>
