import type { Config } from 'tailwindcss'

// Brand design tokens, transcribed from the Figma design system
// (file 4Fw0WGaqVs9iqbLoAAbmpO, "Gold Coast Tokota Website").
// Figma's own style names are noted against each entry so design and code
// stay reconcilable — centralized here rather than as ad hoc utility values.
export default <Partial<Config>>{
  content: [
    './components/**/*.{vue,js,ts}',
    './layouts/**/*.vue',
    './pages/**/*.vue',
    './app.vue',
  ],
  theme: {
    extend: {
      colors: {
        // Figma: 600 — used for the announcement bar and primary buttons.
        ink: '#000000',
        // Figma: 500 — the default body/heading colour, not pure black.
        graphite: '#262626',
        // Figma: 300 — secondary/meta copy.
        muted: '#737373',
        // Figma: 400 — sits between graphite and muted; filter sub-labels and
        // the "View More +" affordance on the listing page.
        subtle: '#4C4C4B',
        // Figma: 200 — hairline borders and input outlines.
        line: '#DDDBDC',
        // Figma: 100 — footer and section backgrounds.
        surface: '#F5F4F4',
        // Figma: Red — reserved for the Sale nav item and sale pricing.
        sale: '#D0021B',
        // The same red lifted for use on the dark chrome. `sale` measures
        // about 2.2:1 against `chrome`, well under the 4.5:1 floor, so the
        // header's Sale item needs its own value rather than a lower-contrast
        // exception. Same hue; only the lightness moves.
        'sale-on-dark': '#FF6B7A',
        // Figma: Timberwolf — the warm stone ground behind the About page's
        // alternating story sections (10:645, 10:648).
        timberwolf: '#E6DED8',

        // --- Template B accent set ---------------------------------------
        // These four do not come from the Figma file above. They come from the
        // approved "Template B" mockup (`Gold Coast Tokota.html` at the repo
        // root) and the brand guidelines PDF, which the customer signed off on
        // after that Figma file was built. Keep them annotated separately so
        // nobody tries to reconcile them against a Figma style name.
        //
        // Brand PDF, "Gold Coast Gold" — the signature accent. An accent only:
        // fills, rules and badges. Never a background for body copy, and never
        // text on white (it fails contrast — use `gold-deep` for that).
        gold: '#D4AF37',
        // Gold as *text on light*. The mockup's own gold text value.
        'gold-deep': '#8A6A1C',
        // Gold as *text on dark* or over photography. The mockup's value.
        'gold-soft': '#E8D9AD',
        // The chrome ground: header, footer and the announcement strip. Not
        // `ink` — `ink` is pure #000 and the whole app already leans on it, so
        // this is a second, softer near-black rather than a redefinition.
        chrome: '#111111',
      },
      fontFamily: {
        // The design specifies Helvetica Neue Light (300) throughout; no
        // webfont is loaded, so this falls back through the standard stack.
        sans: ['"Helvetica Neue"', 'Helvetica', 'Arial', 'sans-serif'],
      },
      fontSize: {
        // [size, { lineHeight, letterSpacing }].
        //
        // The DISPLAY tier is fluid. Each entry is
        // `clamp(mobileMin, intercept + slope*vw, figmaMax)` with the slope
        // solved so the token renders its **exact Figma pixel value at the
        // 1440px design frame** — the number in each comment — while scaling
        // smoothly below that instead of snapping at a breakpoint. Line heights
        // are unitless ratios equal to the Figma pair (e.g. 176/96), so leading
        // tracks the size. Verify with the check in `scripts/check-responsive.mjs`
        // notes: at a 1440px viewport every value below must measure the Figma px.
        //
        // The TEXT tier (body/label/caption/tag/eyebrow/…) stays fixed — small
        // copy should not shrink on a phone.

        // Figma 10:910 — the Sustainability masthead, brand at full width. 96px.
        'display-brand': ['clamp(36px, 18.857px + 5.357vw, 96px)', { lineHeight: '1.8333', letterSpacing: '0' }],
        // Display/800 — the full-bleed About hero statement (Figma 6:631). 70px.
        'display-xl': ['clamp(32px, 21.143px + 3.393vw, 70px)', { lineHeight: '1.2', letterSpacing: '0.2px' }],
        // Editorial hero on the long-form news article (Figma 10:1405). 64px.
        'article-hero': ['clamp(32px, 22.857px + 2.857vw, 64px)', { lineHeight: '1.125', letterSpacing: '0' }],
        // Section headings on Sustainability: "The Latest", "Our Progress",
        // "Follow us on social for more" (Figma 10:916). 54px.
        'display-heading': ['clamp(32px, 25.714px + 1.964vw, 54px)', { lineHeight: '1.3333', letterSpacing: '0' }],
        // Display/500. 46px.
        'display-lg': ['clamp(30px, 25.429px + 1.429vw, 46px)', { lineHeight: '1.2', letterSpacing: '0.92px' }],
        // Display/300 — the About page's opening manifesto paragraph (6:636). 38px.
        'display-statement': ['clamp(20px, 14.857px + 1.607vw, 38px)', { lineHeight: '1.4', letterSpacing: '0.2px' }],
        // Editorial body scale on the news article. 40px.
        'article-lg': ['clamp(24px, 19.429px + 1.429vw, 40px)', { lineHeight: '1.2', letterSpacing: '0' }],
        // Display/400 — headings on the About story sections ("Our ethical
        // approach."). Same metrics as `article-lg` but with Figma's tracking. 40px.
        'display-section': ['clamp(24px, 19.429px + 1.429vw, 40px)', { lineHeight: '1.2', letterSpacing: '0.2px' }],
        // Display/200. 32px.
        'display-md': ['clamp(24px, 21.714px + 0.714vw, 32px)', { lineHeight: '1.25', letterSpacing: '0' }],
        // Display/100. 24px.
        'display-sm': ['clamp(20px, 18.857px + 0.357vw, 24px)', { lineHeight: '1.385', letterSpacing: '0' }],

        // Lede / hero-subtitle copy: 16px on a phone growing to the design's
        // 24px at 1440. Its own token because the display tier's floors are
        // heading-sized, and `text-body lg:text-display-sm` was a hard step.
        lede: ['clamp(16px, 13.714px + 0.714vw, 24px)', { lineHeight: '1.5', letterSpacing: '0' }],

        body: ['16px', { lineHeight: '24px', letterSpacing: '0.64px' }], // Text/400
        label: ['14px', { lineHeight: '16.8px', letterSpacing: '1.4px' }], // Text/300
        'label-link': ['14px', { lineHeight: '20px', letterSpacing: '1.4px' }], // Text/300 - underline
        caption: ['12px', { lineHeight: '16px', letterSpacing: '0.2px' }], // Text/200
        // Text/100 — product badges ("CUSTOM MADE", "RENEWED MATERIALS").
        tag: ['10px', { lineHeight: '16px', letterSpacing: '1px' }],
        // Listing filter group headings ("Category", "Color", "Size").
        'filter-heading': ['14px', { lineHeight: '21px', letterSpacing: '0.42px' }],
        // The soft-cornered buttons on the Sustainability page (10:963).
        action: ['14px', { lineHeight: '21px', letterSpacing: '0.42px' }],
        // Column headings inside the mega menu ("HIGHLIGHTS", "FEATURED SHOPS").
        eyebrow: ['10px', { lineHeight: '16px', letterSpacing: '0.6px' }],
      },
    },
  },
  plugins: [],
}
