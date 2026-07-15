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
      // Same token set as frontend/tailwind.config.ts once Week 2 brand
      // sign-off lands — admin uses a denser variant, not a separate
      // design system (per README Styling Requirements).
      colors: {},
      fontFamily: {},
    },
  },
  plugins: [],
}
