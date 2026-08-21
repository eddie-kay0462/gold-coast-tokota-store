<template>
  <section class="page-gutter section-y mx-auto flex w-full max-w-[1440px] flex-col items-start overflow-hidden">
    <div class="relative flex min-h-[281px] w-full flex-col items-center justify-center gap-5 px-5 py-11">
      <img
        src="/design/sustainability.png"
        alt=""
        aria-hidden="true"
        class="absolute inset-0 size-full object-cover"
        loading="lazy"
      >

      <div class="relative flex w-full flex-col items-center gap-3 text-center text-white">
        <h2 class="w-full text-display-md">We're on a Mission To Build Sustainably</h2>
        <p class="w-full text-body">Read about our progress in our latest Impact Report.</p>
      </div>

      <CommonBrandButton to="/sustainability" variant="white" class="relative">
        Learn More
      </CommonBrandButton>
    </div>
  </section>
</template>
