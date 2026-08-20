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
        // Figma: Timberwolf — the warm stone ground behind the About page's
        // alternating story sections (10:645, 10:648).
        timberwolf: '#E6DED8',
      },
      fontFamily: {
        // The design specifies Helvetica Neue Light (300) throughout; no
        // webfont is loaded, so this falls back through the standard stack.
        sans: ['"Helvetica Neue"', 'Helvetica', 'Arial', 'sans-serif'],
      },
      fontSize: {
        // [size, { lineHeight, letterSpacing }] — values are Figma-exact.
        // The Sustainability masthead — the brand set at full width (10:910).
        'display-brand': ['96px', { lineHeight: '176px', letterSpacing: '0' }],
        // Section headings on the Sustainability page: "The Latest", "Our
        // Progress", "Follow us on social for more" (10:916).
        'display-heading': ['54px', { lineHeight: '72px', letterSpacing: '0' }],
        // Display/800 — the full-bleed About hero statement (Figma 6:631).
        'display-xl': ['70px', { lineHeight: '84px', letterSpacing: '0.2px' }],
        'display-lg': ['46px', { lineHeight: '55.2px', letterSpacing: '0.92px' }], // Display/500
        // Display/300 — the About page's opening manifesto paragraph (6:636).
        'display-statement': ['38px', { lineHeight: '53.2px', letterSpacing: '0.2px' }],
        // Display/400 — headings on the About story sections ("Our ethical
        // approach."). Same metrics as `article-lg` but with Figma's tracking.
        'display-section': ['40px', { lineHeight: '48px', letterSpacing: '0.2px' }],
        // Editorial scale used by the long-form news article (Figma 10:1405).
        'article-lg': ['40px', { lineHeight: '48px', letterSpacing: '0' }],
        'article-hero': ['64px', { lineHeight: '72px', letterSpacing: '0' }],
        'display-md': ['32px', { lineHeight: '40px', letterSpacing: '0' }], // Display/200
        'display-sm': ['24px', { lineHeight: '33.24px', letterSpacing: '0' }], // Display/100
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
