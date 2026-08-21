import type { BlogPost, CmsPage, MediaAsset } from '~/types'
import { avatarFor, daysAgo, int, pick } from './_seed'

const posts: [string, string, boolean][] = [
  ['From Tyre to Tread: How a Discarded Wheel Becomes a Sole', 'The sorting yard in Accra where our soles begin, and why reclaimed rubber outlasts the alternative.', true],
  ['Meet the Makers: A Day at the Bench with Isaaka', 'Our production lead on lasting, hand-stitching, and the twelve years it took to get fast.', true],
  ['Why Every Pair Is Slightly Different (And Why That Matters)', 'Handmade is not a defect. A short guide to reading the marks of the hand in your sandals.', true],
  ['The Ahenema: A Short History of Ghana’s Royal Sandal', 'From Asante courts to the modern last — the form we build on and the heritage it carries.', true],
  ['Sip & Paint: Inside Our Saturday Workshop', 'Three hours, twenty people, and a room that smells of leather and acrylic.', true],
  ['Sizing Guide: Measuring Your Feet at Home in Two Minutes', 'A paper, a pencil, a ruler. How to get your DIY order right the first time.', true],
  ['What Circular Manufacturing Actually Means', 'Less jargon, more specifics: what we divert, what we still cannot use, and what we are working on.', true],
  ['Shipping Sandals to Fourteen Countries', 'What we have learned about customs, courier timelines and packing for a long journey.', false],
  ['Our 2027 Sustainability Targets', 'Draft — numbers still being verified with the production team.', false],
]

export const blogPosts: BlogPost[] = posts.map(([title, excerpt, published], i) => ({
  id: 8000 + i,
  title,
  slug: title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60),
  excerpt,
  body:
    `<p>${excerpt}</p>` +
    '<p>Gold Coast Tokota transforms discarded materials into handcrafted footwear and ' +
    'immersive cultural experiences that preserve heritage while advancing sustainability.</p>' +
    '<h2>Where it starts</h2>' +
    '<p>Every pair begins as something someone threw away. We turn discarded tyres and ' +
    'textile waste into beautiful handcrafted sandals, proving that waste can become ' +
    'something valuable.</p>',
  coverImage: null,
  coverImageAlt: '',
  metaDescription: excerpt.slice(0, 155),
  authorName: pick(['Samuel Kumi-Gyau', 'Mary Seade', 'Akosua Danso']),
  isPublished: published,
  publishedAt: published ? daysAgo(int(3, 200)) : null,
  updatedAt: daysAgo(int(0, 40)),
}))

/**
 * The five CMS pages. `about` is the one README names; the four policy pages
 * are specified in the brand PDF and are legally load-bearing, so their bodies
 * carry the actual published copy rather than placeholder text.
 */
export const cmsPages: CmsPage[] = [
  {
    id: 9001,
    slug: 'about',
    title: 'About Gold Coast Tokota',
    isPolicy: false,
    body:
      '<h2>Our mission</h2>' +
      '<p>To reshape manufacturing in Africa by transforming waste into premium handcrafted ' +
      'footwear, preserving Ghanaian craftsmanship, empowering young people through skills ' +
      'development and creating sustainable cultural experiences that generate lasting social, ' +
      'environmental and economic impact.</p>' +
      '<h2>Our vision</h2>' +
      '<p>To become Africa’s leading sustainable footwear and cultural tourism enterprise, ' +
      'inspiring a future where African craftsmanship, circular manufacturing and cultural ' +
      'heritage drive inclusive economic growth and global recognition.</p>',
    updatedByAdminName: 'Samuel Kumi-Gyau',
    updatedAt: daysAgo(12),
  },
  {
    id: 9002,
    slug: 'shipping-and-delivery',
    title: 'Shipping & Delivery Policy',
    isPolicy: true,
    body:
      '<h2>Domestic shipping (Ghana)</h2><ul>' +
      '<li>Orders are processed within 48 hours after payment confirmation.</li>' +
      '<li>Standard delivery takes 1–2 business days, depending on the destination.</li>' +
      '<li>Delivery is available nationwide through trusted courier partners.</li></ul>' +
      '<h2>International shipping</h2><ul>' +
      '<li>West Africa — 5–10 business days</li>' +
      '<li>Europe — 7–14 business days</li>' +
      '<li>North America — 7–14 business days</li>' +
      '<li>Other destinations — 10–21 business days</li></ul>' +
      '<p>Any customs duties, taxes or import fees imposed by the destination country are the ' +
      'responsibility of the customer.</p>',
    updatedByAdminName: 'Mary Seade',
    updatedAt: daysAgo(30),
  },
  {
    id: 9003,
    slug: 'returns-and-exchanges',
    title: 'Returns & Exchanges Policy',
    isPolicy: true,
    body:
      '<p>Returns are accepted within 7 days of receiving your order if the product is ' +
      'defective, the incorrect item was delivered, or the product arrived damaged.</p>' +
      '<h2>Non-returnable items</h2><ul>' +
      '<li>Custom-made sandals</li><li>Personalised products</li>' +
      '<li>Products damaged through misuse</li>' +
      '<li>Clearance or sale items (unless defective)</li></ul>' +
      '<p>Approved refunds are processed within 7–14 business days using the original ' +
      'payment method.</p>',
    updatedByAdminName: 'Mary Seade',
    updatedAt: daysAgo(30),
  },
  {
    id: 9004,
    slug: 'privacy-policy',
    title: 'Privacy Policy',
    isPolicy: true,
    body:
      '<p>Gold Coast Tokota respects your privacy and is committed to protecting your personal ' +
      'information.</p><h2>Payment security</h2>' +
      '<p>We do not store your payment card information. Payments are securely processed ' +
      'through trusted third-party payment providers.</p>',
    updatedByAdminName: 'Isaac Boateng',
    updatedAt: daysAgo(45),
  },
  {
    id: 9005,
    slug: 'terms-of-service',
    title: 'Terms of Service',
    isPolicy: true,
    body:
      '<p>All products are handcrafted. Minor differences in colour, texture or finish are ' +
      'natural characteristics of handmade products.</p>' +
      '<p>All prices are displayed in Ghana Cedis (GHS) unless otherwise stated.</p>' +
      '<h2>Governing law</h2>' +
      '<p>These Terms of Service are governed by the laws of the Republic of Ghana.</p>',
    updatedByAdminName: 'Isaac Boateng',
    updatedAt: daysAgo(45),
  },
]

const mediaNames = [
  'ahenema-classic-tan-01.jpg', 'ahenema-classic-tan-02.jpg', 'kente-panel-slide-hero.jpg',
  'workshop-bench-isaaka.jpg', 'sip-and-paint-saturday.jpg', 'tyre-sorting-yard.jpg',
  'retread-sole-detail.jpg', 'brand-story-hero.jpg', 'diy-kit-flatlay.jpg',
  'school-tour-group.jpg', 'packaging-detail.jpg', 'accra-workshop-exterior.jpg',
]

export const mediaAssets: MediaAsset[] = mediaNames.map((filename, i) => ({
  id: 9500 + i,
  filename,
  url: '',
  mimeType: 'image/jpeg',
  sizeBytes: int(180, 2400) * 1024,
  width: 1600,
  height: 1067,
  altText: filename.replace(/[-_]/g, ' ').replace(/\.\w+$/, ''),
  uploadedByName: pick(['Mary Seade', 'Akosua Danso', 'Samuel Kumi-Gyau']),
  uploadedAt: daysAgo(int(1, 200)),
}))

export const authorAvatars = Object.fromEntries(
  ['Samuel Kumi-Gyau', 'Mary Seade', 'Akosua Danso'].map((n) => [n, avatarFor(n)]),
)
