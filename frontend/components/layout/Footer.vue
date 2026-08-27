<script setup lang="ts">
import { PhInstagramLogo as InstagramLogo, PhWhatsappLogo as WhatsappLogo } from '@phosphor-icons/vue'
import { useSiteSettingsStore } from '~/stores/siteSettings'

const siteSettings = useSiteSettingsStore()

/**
 * Two link columns, as the approved Template B mockup draws them.
 *
 * This replaces four columns of eighteen links. What went, and why:
 *
 * - **Facebook and Twitter** pointed at `/`. They were placeholders for social
 *   accounts that are not modelled anywhere — only Instagram is, and it moved
 *   to the social row beside the newsletter, where the mockup puts it.
 * - **Two identical "Sitemap" links** both resolved to `/sitemap.xml`. That was
 *   open issue #18; removing both closes it.
 * - **Log In / Sign Up** are reachable from the header's account icon, which is
 *   where people look for them. Both pages are inert until customer auth exists.
 * - The **Company** column duplicated the About page's own section nav (About,
 *   Environmental Initiatives, Factories), which is now one merged page.
 *
 * Some pages lose their only link as a result — see FOR_THE_TEAM.md, which
 * lists them. They still resolve and still appear in the sitemap; they are just
 * no longer advertised from every page on the site.
 */
const columns = computed(() => [
  {
    heading: 'Shop',
    links: [
      { label: 'Best Sellers', to: '/shop?sort=best-selling' },
      { label: 'Sandals', to: '/shop?type=sandals' },
      { label: 'Ahenema', to: '/shop?type=ahenema' },
      { label: 'Bookings', to: '/booking' },
    ],
  },
  {
    heading: 'House',
    links: [
      { label: 'Stories', to: '/blog' },
      { label: 'About', to: '/about' },
      // The mockup names these separately; `/help` is the hub that carries both
      // topics, plus bulk orders and the contact routes.
      { label: 'Shipping & Returns', to: '/help' },
      { label: 'Privacy Policy', to: '/legal/privacy' },
    ],
  },
])

const { href: whatsappHref } = useWhatsApp()

const socials = computed(() => [
  siteSettings.instagramUrl
    ? { label: 'Instagram', href: siteSettings.instagramUrl, icon: InstagramLogo }
    : null,
  whatsappHref.value
    ? { label: 'WhatsApp', href: whatsappHref.value, icon: WhatsappLogo }
    : null,
].filter((entry): entry is { label: string, href: string, icon: typeof InstagramLogo } => !!entry))
</script>

<template>
  <!-- The footer moved to the dark chrome ground with the approved Template B
       mockup. `chrome-dark` switches the focus ring to white, since the base
       graphite ring is invisible here. -->
  <!-- Deeper than it needs to be for its content, deliberately: the link
       columns are four items now, and at the old `pt-12` the whole band read as
       a strip stuck to the bottom of the page rather than a footer. The vertical
       rhythm matches `.section-y` (12 / 16 / 90) so it sits on the same ladder
       as every full-width section above it. -->
  <footer class="chrome-dark flex w-full flex-col items-center bg-chrome px-5 pt-12 text-white md:px-10 md:pt-16 lg:px-[72px] lg:pt-[90px]">
    <!-- The mockup's four tracks: brand, two link sets, newsletter. -->
    <div class="grid w-full gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.4fr] lg:gap-x-14 lg:gap-y-16">
      <div class="flex flex-col items-start gap-5">
        <!-- `min-h-[44px]` keeps the tap target legal: the logo itself is 32px
             tall, and a link wrapping it alone is under the floor. -->
        <NuxtLink to="/" class="-my-1.5 flex min-h-[44px] items-center py-1.5" aria-label="Gold Coast Tokota — home">
          <img
            src="/brand/logo-white.png"
            alt="Gold Coast Tokota"
            class="h-8 w-auto"
            width="435"
            height="108"
            loading="lazy"
          >
        </NuxtLink>

        <p class="max-w-[300px] text-label text-white/60">
          Handcrafted sandals and ahenema, made in Ghana from leather, velvet and
          upcycled materials.
        </p>

        <address class="flex flex-col items-start gap-1 not-italic text-caption text-white/60">
          <!-- Haatso, not the mockup's "Osu": Haatso is the address in the brand
               guidelines, and `/stores` deliberately publishes no street address
               because inventing one would be worse than omitting it.

               Linked rather than plain text — the mockup prints the address, and
               `/stores` is the page that explains visiting the workshop. It also
               keeps that page reachable now the Connect column is gone. -->
          <NuxtLink to="/stores" class="-my-2 flex min-h-[44px] items-center py-2 hover:text-white">
            Haatso, Accra, Ghana
          </NuxtLink>
          <a
            v-if="siteSettings.contactPhone"
            :href="`tel:${siteSettings.contactPhone}`"
            class="-my-2 flex min-h-[44px] items-center py-2 hover:text-white"
          >{{ siteSettings.contactPhone }}</a>
          <a
            v-if="siteSettings.contactEmail"
            :href="`mailto:${siteSettings.contactEmail}`"
            class="-my-2 flex min-h-[44px] items-center py-2 hover:text-white"
          >{{ siteSettings.contactEmail }}</a>
        </address>
      </div>

      <div
        v-for="column in columns"
        :key="column.heading"
        class="flex min-w-0 flex-col items-start gap-4"
      >
        <h2 class="w-full text-eyebrow uppercase tracking-[0.6px] text-white/50">
          {{ column.heading }}
        </h2>
        <ul class="flex w-full flex-col items-start gap-2">
          <li v-for="link in column.links" :key="link.label" class="w-full">
            <NuxtLink
              :to="link.to"
              class="-my-2.5 flex min-h-[44px] items-center py-2.5 text-label text-white/70 hover:text-white"
            >
              {{ link.label }}
            </NuxtLink>
          </li>
        </ul>
      </div>

      <div id="newsletter" class="flex scroll-mt-24 flex-col items-start gap-4">
        <h2 class="w-full text-eyebrow uppercase tracking-[0.6px] text-white/50">Newsletter</h2>
        <p class="text-label text-white/60">Stories and new releases.</p>
        <FormsNewsletterForm source="footer" tone="dark" />

        <ul v-if="socials.length" class="flex flex-wrap items-center gap-5">
          <li v-for="social in socials" :key="social.label">
            <a
              :href="social.href"
              target="_blank"
              rel="noopener noreferrer"
              class="-my-2.5 flex min-h-[44px] items-center gap-2 py-2.5 text-caption uppercase tracking-[1px] text-white/70 hover:text-white"
            >
              <component :is="social.icon" :size="18" />
              {{ social.label }}
            </a>
          </li>
        </ul>
      </div>
    </div>

    <!-- Bottom bar behind a hairline, as the mockup draws it. The tall bottom
         padding below `sm` reserves the corner the fixed WhatsApp button sits
         in, so the last row is never underneath it. -->
    <div class="mt-12 flex w-full flex-col items-center justify-between gap-1 border-t border-white/10 py-8 pb-[calc(2rem+4.5rem)] text-center text-caption text-white/45 sm:flex-row sm:pb-8 lg:mt-16">
      <p>© Gold Coast Tokota {{ new Date().getFullYear() }}. All rights reserved.</p>
      <p>goldcoasttokota.store</p>
    </div>
  </footer>
</template>
