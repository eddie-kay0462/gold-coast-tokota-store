import type { Config } from 'tailwindcss'

/**
 * Admin design tokens.
 *
 * Colour is NOT defined here — it lives once in `assets/css/main.css` as CSS
 * custom properties, and this file only exposes those properties to Tailwind.
 * That is what makes `darkMode: 'class'` cost nothing: `.dark` redefines the
 * variables, and every `bg-bg` / `text-fg` / `border-border` utility in the app
 * follows automatically without a single `dark:` variant.
 *
 * The brand ramp (ink/graphite/muted/subtle/line/surface/sale) is carried over
 * verbatim from `frontend/tailwind.config.ts` so the two apps remain one design
 * system, per README Styling Requirements ("admin may use a denser variant of
 * the same token set, not a wholly separate design system"). The gold/brown/
 * green/sand additions come from the brand PDF's Colour Palette.
 */

// `rgb(var(--x) / <alpha-value>)` is what lets `bg-accent/10` work against a
// custom property. Without the alpha placeholder, opacity utilities silently
// no-op — a trap worth naming, since the failure is invisible.
const token = (name: string) => `rgb(var(--${name}) / <alpha-value>)`

export default <Partial<Config>>{
  darkMode: 'class',
  content: [
    './components/**/*.{vue,js,ts}',
    './layouts/**/*.vue',
    './pages/**/*.vue',
    // The scaffold's globs missed these two; capability maps and formatters
    // both carry class names, and their utilities were being purged.
    './composables/**/*.ts',
    './utils/**/*.ts',
    './fixtures/**/*.ts',
    './app.vue',
    './error.vue',
  ],
  theme: {
    extend: {
      colors: {
        // ---- Semantic (theme-aware) ----
        bg: {
          DEFAULT: token('bg'),
          elevated: token('bg-elevated'),
          sunken: token('bg-sunken'),
          inset: token('bg-inset'),
        },
        fg: {
          DEFAULT: token('fg'),
          strong: token('fg-strong'),
          muted: token('fg-muted'),
          subtle: token('fg-subtle'),
          faint: token('fg-faint'),
        },
        border: {
          DEFAULT: token('border'),
          strong: token('border-strong'),
        },
        primary: {
          DEFAULT: token('primary'),
          fg: token('primary-fg'),
          hover: token('primary-hover'),
        },
        accent: {
          DEFAULT: token('accent'),
          fg: token('accent-fg'),
          soft: token('accent-soft'),
          text: token('accent-text'),
        },
        success: { DEFAULT: token('success'), soft: token('success-soft') },
        warning: { DEFAULT: token('warning'), soft: token('warning-soft') },
        danger: { DEFAULT: token('danger'), soft: token('danger-soft') },
        info: { DEFAULT: token('info'), soft: token('info-soft') },
        neutral: { soft: token('neutral-soft') },

        chart: {
          1: token('chart-1'),
          2: token('chart-2'),
          3: token('chart-3'),
          4: token('chart-4'),
          5: token('chart-5'),
          6: token('chart-6'),
          grid: token('chart-grid'),
          track: token('chart-track'),
        },

        // ---- Brand constants (theme-independent) ----
        // Reach for these only when a value must NOT flip with the theme —
        // a logo lockup, a chart legend swatch printed on paper. Everything
        // else uses the semantic tokens above.
        gold: '#D4AF37',      // PDF: Gold Coast Gold
        brown: '#7A5A3A',     // PDF: Craft Brown
        green: '#2F6F4F',     // PDF: Sustainability Green
        sand: '#EADFC8',      // PDF: Warm Sand
        ink: '#000000',       // PDF: Heritage Black / storefront Figma 600
        graphite: '#262626',  // storefront Figma 500
        muted: '#737373',     // storefront Figma 300
        subtle: '#4C4C4B',    // storefront Figma 400
        line: '#DDDBDC',      // storefront Figma 200
        surface: '#F5F4F4',   // storefront Figma 100
        sale: '#D0021B',      // storefront Figma Red
      },

      fontFamily: {
        // Verbatim from frontend/tailwind.config.ts. No webfont is loaded in
        // either app; the design specifies Helvetica Neue.
        sans: ['"Helvetica Neue"', 'Helvetica', 'Arial', 'sans-serif'],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
      },

      fontSize: {
        // A DENSE, FIXED scale — deliberately not the storefront's fluid
        // display tier. Dashboard text should not grow with the viewport;
        // a 1440px-wide table and a 2560px-wide table want the same 14px row.
        // Sizes measured off the Figma frames at their 1280px width.
        'metric-lg': ['32px', { lineHeight: '40px', letterSpacing: '-0.4px' }], // $16,249
        metric: ['24px', { lineHeight: '32px', letterSpacing: '-0.2px' }],
        title: ['20px', { lineHeight: '28px', letterSpacing: '-0.1px' }],       // page titles
        section: ['16px', { lineHeight: '24px', letterSpacing: '0' }],          // card headings
        ui: ['14px', { lineHeight: '20px', letterSpacing: '0' }],               // the workhorse
        meta: ['12px', { lineHeight: '16px', letterSpacing: '0.1px' }],         // timestamps, labels
        micro: ['10px', { lineHeight: '14px', letterSpacing: '0.4px' }],        // badges, counters
      },

      borderRadius: {
        sm: 'var(--radius-sm)',
        DEFAULT: 'var(--radius)',
        lg: 'var(--radius-lg)',
        pill: 'var(--radius-pill)',
      },

      spacing: {
        sidebar: 'var(--sidebar-w)',
        'sidebar-collapsed': 'var(--sidebar-collapsed-w)',
        header: 'var(--header-h)',
        rail: 'var(--rail-w)',
        'rail-panel': 'var(--rail-panel-w)',
      },

      // The kit is flat — hairline borders, no drop shadows. The only
      // exception is content that floats above the page and needs to read as
      // detached: dropdowns, the command palette, toasts.
      boxShadow: {
        overlay: '0 8px 32px -8px rgb(0 0 0 / 0.18), 0 0 0 1px rgb(var(--border))',
        popover: '0 4px 16px -4px rgb(0 0 0 / 0.12), 0 0 0 1px rgb(var(--border))',
      },

      transitionDuration: {
        DEFAULT: '150ms',
      },

      zIndex: {
        // Documented ladder, mirroring the storefront's convention in
        // frontend/layouts/default.vue.
        //
        // Order matters and has bitten once already: `scrim` must sit BELOW
        // `sidebar`/`drawer`, or the overlay swallows clicks on the very panel
        // it was dimming the page for.
        rail: '30',
        header: '45',
        scrim: '50',
        sidebar: '55',
        drawer: '55',
        modal: '60',
        popover: '70',
        toast: '80',
      },
    },
  },
  plugins: [],
}
