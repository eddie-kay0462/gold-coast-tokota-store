/**
 * The About page's content: its shape, and the copy it falls back on.
 *
 * The page is edited in the admin (Content › Pages › About) and arrives as
 * `GET /pages/about` → `data.content`, merged over defaults by the API
 * (App\Support\AboutPage). These defaults are the same copy, used only when
 * the API can't be reached, so the page never renders half-empty. If you change
 * a default here, change it in AboutPage::defaults() too.
 *
 * Sources for the copy: GOLD_COAST_TOKOTA.md, the brand's channels (Instagram
 * @goldcoasttokota, YouTube @GoldCoastTokota) and Ghanaian business press,
 * researched 23 Oct 2026. Press figures carry their date.
 */

interface Section { visible: boolean }
interface ImageBlock { image: string, imageId: number | null, imageAlt: string, imageUrl?: string }

export interface SocialPost {
  platform: 'youtube' | 'instagram'
  /** YouTube video id, or Instagram reel shortcode. */
  id: string
  title: string
  /** Who posted it, and how long it runs where known. */
  meta: string
  /** Shorts and reels play portrait; interviews 16:9. */
  shape: 'portrait' | 'landscape'
  /** YouTube still: `oardefault` (portrait, some Shorts), `maxresdefault` (1280px), else `hqdefault`. */
  thumb: 'oardefault' | 'maxresdefault' | null
}

export interface AboutContent {
  hero: Section & ImageBlock & { eyebrow: string, heading: string, lede: string }
  statement: Section & { text: string }
  story: Section & { eyebrow: string, heading: string, paragraphs: string[], quote: string, quoteBy: string, quoteSource: string, quoteUrl: string }
  figures: Section & { items: { value: string, label: string, source: string, href: string }[] }
  materials: Section & { eyebrow: string, heading: string, body: string, items: { name: string, body: string }[] }
  people: Section & ImageBlock & { eyebrow: string, heading: string, body: string, caption: string, team: { role: string, name: string }[] }
  ahenema: Section & ImageBlock & { eyebrow: string, heading: string, body: string }
  experiences: Section & { eyebrow: string, heading: string, body: string, items: { name: string, when: string, detail: string }[] }
  watch: Section & { eyebrow: string, heading: string, body: string, posts: SocialPost[] }
  slogans: Section & { items: string[] }
  recognition: Section & { eyebrow: string, heading: string, items: { name: string, detail: string, date: string, href: string }[] }
  mission: Section & { heading: string, lede: string, mission: string, vision: string }
  socials: { instagram: string, youtube: string }
}

const SPRINGBOARD = 'https://www.springboard.com.gh/2026/05/25/african-brands-must-compete-globally-through-innovation-and-culture-says-gold-coast-tokota-founder/'
const BUSINESS_BEAT = 'https://businessbeat24.com/gold-coast-tokota-eco-friendly-solutions-one-shoe-at-a-time/'

export const ABOUT_DEFAULTS: AboutContent = {
  hero: {
    visible: true,
    eyebrow: 'About Gold Coast Tokota',
    heading: 'Crafted with purpose.\nInspired by culture.',
    lede: 'We turn discarded tyres, water sachets and old denim into handcrafted ahenema and sandals in Haatso, Accra, and invite people into the workshop to see how it’s done.',
    image: '/design/about-hero-ahotoso.webp',
    imageId: null,
    imageAlt: 'The Ahotoso ahenema: black leather with gold crowns, handmade by Gold Coast Tokota',
  },
  statement: { visible: true, text: 'Handmade in Ghana, one pair at a time.' },
  story: {
    visible: true,
    eyebrow: 'Our story',
    heading: 'It started with a pair of sandals that let him down.',
    paragraphs: [
      'On the way to meet a mentor, our founder Samuel Kumi-Gyau’s sandals gave way, and he missed the meeting. In 2022 he decided Ghana deserved footwear that was made here and made to last, and set out to build the brand himself.',
      'Before the first pair, he travelled outside Ghana to learn how customers abroad saw African footwear. In 2023 Gold Coast Tokota opened its workshop in Haatso, Accra, making ahenema, the sandal of Ghanaian royalty, and sandals for everyday wear, from leather, velvet and materials other people throw away.',
      'Today the workshop is also a classroom. Schools, companies and visitors come to paint, stitch and see how waste becomes something worth wearing.',
    ],
    quote: 'We are changing the narrative about manufacturing in Africa.',
    quoteBy: 'Samuel Kumi-Gyau, Founder & CEO',
    quoteSource: 'Springboard Hangout, May 2026',
    quoteUrl: SPRINGBOARD,
  },
  figures: {
    visible: true,
    items: [
      { value: '2,500+', label: 'pairs made and sold across Africa, Europe and Asia', source: 'Business Beat, Feb 2025', href: BUSINESS_BEAT },
      { value: '5 tonnes', label: 'of tyres and denim turned into footwear', source: 'Business Beat, Feb 2025', href: BUSINESS_BEAT },
      { value: '5–25%', label: 'of every pair is reclaimed fabric or tyre', source: 'Ghana Climate Innovation Centre', href: 'https://ghanacic.ashesi.edu.gh/ventures/gold-coast-tokota/' },
      { value: '20', label: 'pairs a day, each one made by hand', source: 'Springboard, May 2026', href: SPRINGBOARD },
    ],
  },
  materials: {
    visible: true,
    eyebrow: 'What we make them from',
    heading: 'Waste isn’t waste until we waste it.',
    body: 'Tyres, water sachets and worn-out denim are thrown away across Ghana every day. We collect them and work them into footwear by hand, so less goes to landfill and less new leather goes into each pair.',
    items: [
      { name: 'Car tyres', body: 'Collected from FITA Autotech’s service stations before they reach a landfill, and cut into hard-wearing soles.' },
      { name: 'Water sachets', body: 'Ocean-bound plastic: the drinking-water sachets Ghana gets through every day, kept out of the gutters.' },
      { name: 'Denim', body: 'Old jeans and textile offcuts, given a second life in the pair instead of a dump.' },
      { name: 'Leather and velvet', body: 'Finished by hand into the crowns, straps and linings of each pair of ahenema.' },
    ],
  },
  people: {
    visible: true,
    eyebrow: 'The people',
    heading: 'Made by hand, by artisans we train.',
    body: 'Our workshop trains and employs young people, women and people living with disabilities as skilled artisans. Every pair passes through their hands, from the first cut of the sole to the last stitch.',
    image: '/design/post-tyred-hero.png',
    imageId: null,
    imageAlt: 'The Gold Coast Tokota team in the workshop, holding their sandals, with two of the FITA Autotech team, under the brand’s Africa map',
    caption: 'The Gold Coast Tokota team with FITA Autotech, who supply the tyres.',
    team: [
      { role: 'Founder & CEO', name: 'Samuel Kumi-Gyau' },
      { role: 'Operations Lead', name: 'Mary Seade' },
    ],
  },
  ahenema: {
    visible: true,
    eyebrow: 'The ahenema',
    heading: 'Fit for royalty,\nmade for every day.',
    body: 'Ahenema have been worn by Ghanaian royalty for generations. We keep the shape, the crowns and the Adinkra symbols, and build them from reclaimed materials, leather and velvet. Now we’re making Orthopaedic Ahenema too, so the royal sandal can be worn all day.',
    image: '/design/about-ahenema-kentehene.webp',
    imageId: null,
    imageAlt: 'The Kentehene ahenema: a blue suede footbed with a woven kente strap and leather crown',
  },
  experiences: {
    visible: true,
    eyebrow: 'Experiences',
    heading: 'Come and make a pair with us.',
    body: 'The workshop is open to everyone: paint your own sandals, spend a day at the bench, or bring a class to see how waste becomes footwear.',
    items: [
      { name: 'Sandal Sip & Paint', when: 'Every Saturday · 10:00 AM – 1:00 PM', detail: 'Paint your own pair with Adinkra symbols. Up to 20 people.' },
      { name: 'Be a Shoemaker for a Day', when: 'Every Friday · 9:00 AM – 4:00 PM', detail: 'A full day at the bench with our artisans. Up to 10 people.' },
      { name: 'School Sustainability Tours', when: 'Monday – Friday', detail: 'How waste becomes footwear, for up to 40 students.' },
      { name: 'Corporate Team Building', when: 'By appointment', detail: 'Half or full day, up to 30 people.' },
    ],
  },
  watch: {
    visible: true,
    eyebrow: 'From the workshop',
    heading: 'See the work for yourself.',
    body: 'Pairs being made, classrooms on a visit, Sip & Paint afternoons, and the founder in conversation, from our Instagram and YouTube.',
    posts: [
      { platform: 'youtube', id: 'sPsziRAI9JE', title: 'Trash to handcrafted ahenema', meta: 'Gold Coast Tokota · YouTube Short', shape: 'portrait', thumb: 'maxresdefault' },
      { platform: 'instagram', id: 'DdmepfdOMlX', title: 'Nubuke tries the tyre shoe', meta: 'Nubuke × Gold Coast Tokota · Instagram', shape: 'portrait', thumb: null },
      { platform: 'instagram', id: 'DWlMVGmDcF-', title: 'Edlys Montessori pupils visit the workshop', meta: 'Gold Coast Tokota · Instagram', shape: 'portrait', thumb: null },
      { platform: 'youtube', id: 'lkWkuDK7a7s', title: 'Paint your story. Wear your story.', meta: 'Sandal Sip & Paint · YouTube Short', shape: 'portrait', thumb: 'oardefault' },
      { platform: 'instagram', id: 'DY1nm0oNGLa', title: 'Africa Day Sip & Paint with MTN Ghana', meta: 'Gold Coast Tokota · Instagram', shape: 'portrait', thumb: null },
      { platform: 'youtube', id: 'jotLR4hzqPE', title: 'DIY: shoemaking with Gold Coast Tokota', meta: 'Adom TV, Life & Living It · 7 min', shape: 'landscape', thumb: 'maxresdefault' },
      { platform: 'instagram', id: 'DUDYvAgjRUj', title: 'Ahenema on a global stage, with Wode Maya', meta: 'Gold Coast Tokota · Instagram', shape: 'portrait', thumb: null },
      { platform: 'youtube', id: 'H1z2YRpgDII', title: 'From trash to treasure', meta: 'Conrado TV · 36 min', shape: 'landscape', thumb: 'maxresdefault' },
      { platform: 'youtube', id: 'X1sbKOc_VTA', title: 'Orange Corners Ghana: Samuel Kumi-Gyau', meta: 'Joy Business · 1 min', shape: 'landscape', thumb: null },
      { platform: 'youtube', id: 'zPEedEZoNos', title: 'Entrepreneurship & cultural heritage', meta: 'Springboard Hangout, Joy Prime · 32 min', shape: 'landscape', thumb: null },
      { platform: 'youtube', id: 'PHqE6nU1xo0', title: 'The GH₵2,000 decision that changed everything', meta: 'The Next Africa · 67 min', shape: 'landscape', thumb: 'maxresdefault' },
    ],
  },
  // Off since 23 Oct at the client's request; switch it back on in the admin.
  slogans: { visible: false, items: ['Crafted with Purpose', 'Inspired by Culture', 'Made in Ghana'] },
  recognition: {
    visible: true,
    eyebrow: 'Recognition',
    heading: 'Backed by people who build businesses.',
    items: [
      { name: 'Orange Corners Ghana', detail: 'Cohort 9 of the acceleration programme of the Kingdom of the Netherlands, run by GrowthAfrica', date: '', href: 'https://www.youtube.com/watch?v=X1sbKOc_VTA' },
      { name: 'Ghana Climate Innovation Centre', detail: 'Cohort 10 of its green-economy incubator at Ashesi University', date: 'Dec 2024', href: 'https://ghanacic.ashesi.edu.gh/ventures/gold-coast-tokota/' },
      { name: 'BlueInvest Africa', detail: 'Blue Economy Program award', date: 'Oct 2025', href: 'https://magazine.thenextafrica.com/gold-coast-tokota-scoops-blue-economy-program-award/' },
      { name: 'Africa Business Bureau', detail: 'Third place, Anchor Incubator pitch day', date: 'Dec 2025', href: 'https://ghanaiantimes.com.gh/abb-hosts-successful-pitch-event-for-the-second-cohort-of-the-anchor-incubator-project/' },
      { name: 'YEF Entrepreneurship Challenge', detail: 'Sixth place, with GH₵10,000 of advertising support', date: 'Apr 2026', href: 'https://thebftonline.com/2026/04/18/gold-coast-tokota-to-leverage-gh%C2%A210000-yef-advertising-support-for-visibility-growth/' },
    ],
  },
  mission: {
    visible: true,
    heading: 'Where we’re going.',
    lede: 'A future where African manufacturing creates value for people, culture and the planet.',
    mission: 'To reshape manufacturing in Africa by transforming waste into premium handcrafted footwear, preserving Ghanaian craftsmanship, empowering young people through skills development and creating sustainable cultural experiences that generate lasting social, environmental and economic impact.',
    vision: 'To become Africa’s leading sustainable footwear and cultural tourism enterprise, inspiring a future where African craftsmanship, circular manufacturing and cultural heritage drive inclusive economic growth and global recognition.',
  },
  socials: {
    instagram: 'https://www.instagram.com/goldcoasttokota/',
    youtube: 'https://www.youtube.com/@GoldCoastTokota',
  },
}

/** The API's content over the defaults, section by section. */
export function withAboutDefaults(content?: Partial<AboutContent> | null): AboutContent {
  const out = {} as Record<string, unknown>
  for (const key of Object.keys(ABOUT_DEFAULTS) as (keyof AboutContent)[]) {
    out[key] = { ...ABOUT_DEFAULTS[key], ...(content?.[key] ?? {}) }
  }
  return out as unknown as AboutContent
}

/** The image to show: the API's resolved URL, else the designed default. */
export function aboutImage(block: ImageBlock): string {
  return block.imageUrl || block.image
}
