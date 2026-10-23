/**
 * The About page's facts, in one place, each with where it came from.
 *
 * Sources: `GOLD_COAST_TOKOTA.md` (the brand's own reference: mission, vision,
 * voice, workshops, roles), the brand's own channels (Instagram
 * @goldcoasttokota, YouTube @GoldCoastTokota, Threads), and Ghanaian business
 * press. Press figures are what the founder told the reporter at the time, so
 * each one carries its date; update them here when the brand gives new ones.
 * Researched 23 Oct 2026.
 */

/** The brand's own accounts (from its Linktree, linktr.ee/goldcoasttokota). */
export const BRAND_SOCIALS = {
  instagram: 'https://www.instagram.com/goldcoasttokota/',
  youtube: 'https://www.youtube.com/@GoldCoastTokota',
  tiktok: 'https://www.tiktok.com/@goldcoasttokota',
  x: 'https://x.com/goldcoasttokota',
} as const

export interface AboutFigure {
  value: string
  label: string
  /** When and where the figure was given, shown under it. */
  source: string
  href: string
}

export const ABOUT_FIGURES: AboutFigure[] = [
  {
    value: '2,500+',
    label: 'pairs made and sold across Africa, Europe and Asia',
    source: 'Business Beat, Feb 2025',
    href: 'https://businessbeat24.com/gold-coast-tokota-eco-friendly-solutions-one-shoe-at-a-time/',
  },
  {
    value: '5 tonnes',
    label: 'of tyres and denim turned into footwear',
    source: 'Business Beat, Feb 2025',
    href: 'https://businessbeat24.com/gold-coast-tokota-eco-friendly-solutions-one-shoe-at-a-time/',
  },
  {
    value: '5–25%',
    label: 'of every pair is reclaimed fabric or tyre',
    source: 'Ghana Climate Innovation Centre',
    href: 'https://ghanacic.ashesi.edu.gh/ventures/gold-coast-tokota/',
  },
  {
    value: '20',
    label: 'pairs a day, each one made by hand',
    source: 'Springboard, May 2026',
    href: 'https://www.springboard.com.gh/2026/05/25/african-brands-must-compete-globally-through-innovation-and-culture-says-gold-coast-tokota-founder/',
  },
]

/** What the soles and uppers are made from (press and the brand's own posts). */
export const ABOUT_MATERIALS = [
  {
    name: 'Car tyres',
    body: 'Collected from FITA Autotech’s service stations before they reach a landfill, and cut into hard-wearing soles.',
  },
  {
    name: 'Water sachets',
    body: 'Ocean-bound plastic: the drinking-water sachets Ghana gets through every day, kept out of the gutters.',
  },
  {
    name: 'Denim',
    body: 'Old jeans and textile offcuts, given a second life in the pair instead of a dump.',
  },
  {
    name: 'Leather and velvet',
    body: 'Finished by hand into the crowns, straps and linings of each pair of ahenema.',
  },
]

/** GOLD_COAST_TOKOTA.md §15. Times and capacities quoted as written there. */
export const ABOUT_EXPERIENCES = [
  { name: 'Sandal Sip & Paint', when: 'Every Saturday · 10:00 AM – 1:00 PM', detail: 'Paint your own pair with Adinkra symbols. Up to 20 people.' },
  { name: 'Be a Shoemaker for a Day', when: 'Every Friday · 9:00 AM – 4:00 PM', detail: 'A full day at the bench with our artisans. Up to 10 people.' },
  { name: 'School Sustainability Tours', when: 'Monday – Friday', detail: 'How waste becomes footwear, for up to 40 students.' },
  { name: 'Corporate Team Building', when: 'By appointment', detail: 'Half or full day, up to 30 people.' },
]

export interface Recognition {
  name: string
  detail: string
  /** Empty when no date is published. */
  date: string
  href: string
}

export const ABOUT_RECOGNITION: Recognition[] = [
  {
    name: 'Orange Corners Ghana',
    detail: 'Cohort 9 of the acceleration programme of the Kingdom of the Netherlands, run by GrowthAfrica',
    date: '',
    href: 'https://www.youtube.com/watch?v=X1sbKOc_VTA',
  },
  {
    name: 'Ghana Climate Innovation Centre',
    detail: 'Cohort 10 of its green-economy incubator at Ashesi University',
    date: 'Dec 2024',
    href: 'https://ghanacic.ashesi.edu.gh/ventures/gold-coast-tokota/',
  },
  {
    name: 'BlueInvest Africa',
    detail: 'Blue Economy Program award',
    date: 'Oct 2025',
    href: 'https://magazine.thenextafrica.com/gold-coast-tokota-scoops-blue-economy-program-award/',
  },
  {
    name: 'Africa Business Bureau',
    detail: 'Third place, Anchor Incubator pitch day',
    date: 'Dec 2025',
    href: 'https://ghanaiantimes.com.gh/abb-hosts-successful-pitch-event-for-the-second-cohort-of-the-anchor-incubator-project/',
  },
  {
    name: 'YEF Entrepreneurship Challenge',
    detail: 'Sixth place, with GH₵10,000 of advertising support',
    date: 'Apr 2026',
    href: 'https://thebftonline.com/2026/04/18/gold-coast-tokota-to-leverage-gh%C2%A210000-yef-advertising-support-for-visibility-growth/',
  },
]

export interface SocialPost {
  platform: 'youtube' | 'instagram'
  /** YouTube video id, or Instagram reel shortcode. */
  id: string
  title: string
  /** Who posted it, and how long it runs where known. */
  meta: string
  /** `short` / `reel` play portrait; `video` plays 16:9. */
  shape: 'portrait' | 'landscape'
  /**
   * YouTube still to show: `oardefault` is the portrait one a Short may have,
   * `maxresdefault` the 1280px one. Defaults to `hqdefault` (480px, always there).
   * Checked per video on 23 Oct.
   */
  thumb?: 'oardefault' | 'maxresdefault'
}

/**
 * "From the workshop": the brand's own posts first, then press and TV.
 * Every YouTube id was checked against YouTube's oEmbed (embeddable); the
 * Instagram reels are the brand's own, and the titles say what their captions
 * say.
 */
export const ABOUT_POSTS: SocialPost[] = [
  { platform: 'youtube', id: 'sPsziRAI9JE', title: 'Trash to handcrafted ahenema', meta: 'Gold Coast Tokota · YouTube Short', shape: 'portrait', thumb: 'maxresdefault' },
  // A collaboration posted by the creator Nubuke (@nubuke), with
  // @goldcoasttokota as collaborator: the tyre shoe, and an event at the
  // workshop on 4 Oct 2026. Added at the client's request.
  { platform: 'instagram', id: 'DdmepfdOMlX', title: 'Nubuke tries the tyre shoe', meta: 'Nubuke × Gold Coast Tokota · Instagram', shape: 'portrait' },
  { platform: 'instagram', id: 'DWlMVGmDcF-', title: 'Edlys Montessori pupils visit the workshop', meta: 'Gold Coast Tokota · Instagram', shape: 'portrait' },
  { platform: 'youtube', id: 'lkWkuDK7a7s', title: 'Paint your story. Wear your story.', meta: 'Sandal Sip & Paint · YouTube Short', shape: 'portrait', thumb: 'oardefault' },
  { platform: 'instagram', id: 'DY1nm0oNGLa', title: 'Africa Day Sip & Paint with MTN Ghana', meta: 'Gold Coast Tokota · Instagram', shape: 'portrait' },
  { platform: 'youtube', id: 'jotLR4hzqPE', title: 'DIY: shoemaking with Gold Coast Tokota', meta: 'Adom TV, Life & Living It · 7 min', shape: 'landscape', thumb: 'maxresdefault' },
  { platform: 'instagram', id: 'DUDYvAgjRUj', title: 'Ahenema on a global stage, with Wode Maya', meta: 'Gold Coast Tokota · Instagram', shape: 'portrait' },
  { platform: 'youtube', id: 'H1z2YRpgDII', title: 'From trash to treasure', meta: 'Conrado TV · 36 min', shape: 'landscape', thumb: 'maxresdefault' },
  { platform: 'youtube', id: 'X1sbKOc_VTA', title: 'Orange Corners Ghana: Samuel Kumi-Gyau', meta: 'Joy Business · 1 min', shape: 'landscape' },
  { platform: 'youtube', id: 'zPEedEZoNos', title: 'Entrepreneurship & cultural heritage', meta: 'Springboard Hangout, Joy Prime · 32 min', shape: 'landscape' },
  { platform: 'youtube', id: 'PHqE6nU1xo0', title: 'The GH₵2,000 decision that changed everything', meta: 'The Next Africa · 67 min', shape: 'landscape', thumb: 'maxresdefault' },
]
