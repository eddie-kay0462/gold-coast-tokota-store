<template>
  <!-- Figma 6:635 — full-bleed workshop photograph with the brand statement
       centred over it. The image is decorative; the heading carries the page. -->
  <section class="relative flex w-full flex-col items-center justify-center overflow-hidden px-5 py-24 lg:h-[691px] lg:py-0">
    <img
      src="/design/about-hero.png"
      alt=""
      aria-hidden="true"
      class="absolute inset-0 size-full object-cover"
      width="2000"
      height="1246"
    >

    <div class="relative flex w-full max-w-[488px] flex-col items-center gap-4 text-center font-light text-white">
      <!-- The designed line breaks are wider than the 488px column at 70px, so
           the heading is allowed to run past it rather than rewrap. -->
      <h1 class="w-full text-display-md lg:w-auto lg:whitespace-nowrap lg:text-display-xl">
        We believe<br>we can all make<br>a difference.
      </h1>
      <p class="w-full text-body lg:text-display-sm">
        Our way: Exceptional quality.<br>Ethical factories. Radical Transparency.
      </p>
    </div>
  </section>
</template>
