import type { Config } from 'tailwindcss'

export default <Partial<Config>>{
  content: [
    './components/**/*.{vue,js,ts}',
    './layouts/**/*.vue',
    './pages/**/*.vue',
    './app.vue',
  ],
  theme: {
    extend: {
      // Brand design tokens (colors, spacing, typography) land here once
      // Week 2 design sign-off is complete. Centralized here so storefront
      // and admin share one token set rather than ad hoc utility values.
      colors: {},
      fontFamily: {},
    },
  },
  plugins: [],
}
