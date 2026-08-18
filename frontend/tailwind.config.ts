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
        // Figma: 200 — hairline borders and input outlines.
        line: '#DDDBDC',
        // Figma: 100 — footer and section backgrounds.
        surface: '#F5F4F4',
        // Figma: Red — reserved for the Sale nav item and sale pricing.
        sale: '#D0021B',
      },
      fontFamily: {
        // The design specifies Helvetica Neue Light (300) throughout; no
        // webfont is loaded, so this falls back through the standard stack.
        sans: ['"Helvetica Neue"', 'Helvetica', 'Arial', 'sans-serif'],
      },
      fontSize: {
        // [size, { lineHeight, letterSpacing }] — values are Figma-exact.
        'display-lg': ['46px', { lineHeight: '55.2px', letterSpacing: '0.92px' }], // Display/500
        'display-md': ['32px', { lineHeight: '40px', letterSpacing: '0' }], // Display/200
        'display-sm': ['24px', { lineHeight: '33.24px', letterSpacing: '0' }], // Display/100
        body: ['16px', { lineHeight: '24px', letterSpacing: '0.64px' }], // Text/400
        label: ['14px', { lineHeight: '16.8px', letterSpacing: '1.4px' }], // Text/300
        'label-link': ['14px', { lineHeight: '20px', letterSpacing: '1.4px' }], // Text/300 - underline
        caption: ['12px', { lineHeight: '16px', letterSpacing: '0.2px' }], // Text/200
      },
    },
  },
  plugins: [],
}
