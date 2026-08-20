/**
 * News & Events content as drawn in Figma (listing node 10:906, article node
 * 10:1405).
 *
 * Feature 9 owns the real blog CMS (`GET /api/v1/blog-posts`); until it exists
 * these stand in so both pages render their designed state. Copy, dates and
 * photography are transcribed from the Figma frames — only the Tyred of Waste
 * article is drawn in full, so it is the only entry with body blocks. The
 * others carry listing metadata only, and their detail pages say so rather
 * than inventing an article.
 */

/** A rendered article is a sequence of rich-text and full-bleed image blocks. */
export type PostBlock =
  | { type: 'html', html: string }
  | { type: 'image', src: string, alt: string }

export type ApiPost = {
  slug: string
  title: string
  /** Deck shown under the title on the article hero. */
  subtitle?: string | null
  /** Pill above the hero title, e.g. "Partnerships". */
  category?: string | null
  /** ISO 8601. Rendered as "21st March 2025". */
  published_at: string
  /** Listing thumbnail. */
  cover_image?: string
  /** Full-bleed article hero; falls back to `cover_image`. */
  hero_image?: string
  /** Opening statement, set large above the article body. */
  lede?: string | null
  blocks?: PostBlock[]
  /** Plain rich-text body, as the CMS will return it. Used when `blocks` is absent. */
  body?: string | null
}

export const DESIGN_POSTS: ApiPost[] = [
  {
    slug: 'tyred-of-waste',
    title: 'Tyred of Waste',
    subtitle: 'Partnership with Fita Autotech to Tackle Tyre Waste',
    category: 'Partnerships',
    published_at: '2025-03-21',
    cover_image: '/design/news-tyred.png',
    hero_image: '/design/post-tyred-hero.png',
    lede:
      'Imagine a world where nothing goes to waste, where old tyres don’t just pile up in '
      + 'landfills but instead find new life as something beautiful and functional. That world is '
      + 'becoming a reality thanks to an inspiring collaboration between two innovative Ghanaian '
      + 'brands: FITA Autotech and Gold Coast Tokota.',
    blocks: [
      { type: 'image', src: '/design/news-tyred.png', alt: 'The FITA Autotech and Gold Coast Tokota teams together' },
      {
        type: 'html',
        html: `
          <h2>Orange Corners Ghana</h2>
          <p>Both companies, alumni of Cohort 9’s <a href="https://www.orangecorners.com/country/ghana/" target="_blank" rel="noopener noreferrer">Orange Corners Ghana</a> acceleration programme — an initiative of the Kingdom of the Netherlands, implemented by <a href="https://growthafrica.com/" target="_blank" rel="noopener noreferrer">GrowthAfrica</a> — have joined forces to tackle both environmental and social challenges through a simple yet powerful idea: turning used tyres into eco-friendly footwear and accessories.</p>

          <h2>Why This Matters</h2>

          <h3>Reducing Waste</h3>
          <p>Tyres are notoriously difficult to dispose of, and improper disposal harms the environment. This initiative gives them a second life.</p>

          <h3>Empowering Artisans</h3>
          <p>Gold Coast Tokota provides employment and training for skilled artisans, including people with disabilities, giving them the opportunity to create high-quality handcrafted products.</p>

          <h3>Sustainable Business Models</h3>
          <p>This partnership proves that businesses can be both profitable and purpose-driven, making a tangible impact on communities.</p>

          <h3>Championing Innovation</h3>
          <p>Turning discarded materials into valuable products is the future of sustainability — and these two brands are leading the way.</p>
        `,
      },
      { type: 'image', src: '/design/post-tyred-hero.png', alt: 'Artisans and the FITA Autotech team outside the workshop' },
      {
        type: 'html',
        html: `
          <h2>The Process</h2>
          <p><a href="https://www.linkedin.com/company/fitaautotech/" target="_blank" rel="noopener noreferrer">FITA Autotech</a>, a leader in sustainable mobility solutions, collects used tyres from its auto service stations — tyres that would otherwise be discarded as waste.</p>
          <p>Gold Coast Tokota, a brand dedicated to inclusive artisan craftsmanship, repurposes these tyres into stylish, durable, eco-friendly footwear and accessories.</p>

          <h2>More than just Recycling</h2>
          <p>By working together, FITA Autotech and Gold Coast Tokota are setting an example of how businesses can think beyond profit and create real change. With creativity, collaboration, and a shared commitment to sustainability, they are proving that waste isn’t waste, until we waste it.</p>
          <p>Congratulations to FITA Autotech and Gold Coast Tokota for driving sustainability forward.</p>
        `,
      },
    ],
  },
  {
    slug: 'konversations-with-miss-africa-to-be',
    title: 'Konversations with Miss Africa To Be!',
    category: 'Events',
    published_at: '2025-02-19',
    cover_image: '/design/news-konversations.png',
  },
  {
    slug: 'celebrating-au-day',
    title: 'Celebrating AU Day: A Memo',
    category: 'Community',
    published_at: '2026-05-25',
    cover_image: '/design/news-au-day.png',
  },
  {
    slug: 'sandal-sip-and-paint',
    title: 'Sandal Sip and Paint Session on 1st May 2026',
    category: 'Events',
    published_at: '2026-04-01',
    cover_image: '/design/news-sip-paint.png',
  },
  {
    slug: 'impact-over-profit',
    title: 'Impact over Profit: A Bold Step Towards a Greener Future',
    category: 'Sustainability',
    published_at: '2025-04-27',
    cover_image: '/design/news-impact.png',
  },
  {
    slug: 'afroquality-partnership',
    title: 'Gold Coast Tokota x AfroQuality: An Exciting New Partnership',
    category: 'Partnerships',
    published_at: '2025-06-20',
    cover_image: '/design/news-partnership.png',
  },
  {
    slug: 'springboard-entrepreneurship-cultural-heritage',
    title: 'Springboard: Entrepreneurship and Cultural Heritage',
    category: 'Community',
    published_at: '2026-05-23',
    cover_image: '/design/news-springboard.png',
  },
  {
    slug: 'orange-corners-cohort-9',
    title: 'Gold Coast Tokota Joins 9th Cohort of Orange Corners',
    category: 'Community',
    published_at: '2024-11-04',
    cover_image: '/design/news-placeholder.png',
  },
]

/** The three cards under an article (Figma section 08). */
export const RELATED_POSTS: ApiPost[] = [
  {
    slug: 'changes-to-packaging',
    title: 'Changes to Packaging',
    category: 'Sustainability',
    published_at: '2026-02-10',
    cover_image: '/design/news-packaging.png',
  },
  {
    slug: 'our-employment-initiatives',
    title: 'Our Employment Initiatives',
    category: 'Careers',
    published_at: '2026-01-22',
    cover_image: '/design/news-employment.png',
  },
  {
    slug: 'our-carbon-commitment',
    title: 'Our Carbon Commitment',
    category: 'Sustainability',
    published_at: '2025-12-05',
    cover_image: '/design/news-carbon.png',
  },
]

/** "21st March 2025" — the date format used throughout the news designs. */
export function formatPostDate(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''

  const day = date.getUTCDate()
  const suffix
    = day % 10 === 1 && day !== 11
      ? 'st'
      : day % 10 === 2 && day !== 12
        ? 'nd'
        : day % 10 === 3 && day !== 13
          ? 'rd'
          : 'th'

  const month = date.toLocaleDateString('en-GB', { month: 'long', timeZone: 'UTC' })
  return `${day}${suffix} ${month} ${date.getUTCFullYear()}`
}
