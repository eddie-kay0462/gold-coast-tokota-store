/**
 * Built-in copy for the policy, help and accessibility pages.
 *
 * Each page fetches `GET /pages/{slug}` and falls back to the entry here when
 * the CMS has nothing approved. When the owner publishes a page from admin
 * (Content › Pages), that copy wins and the entry below stops rendering.
 *
 * Two kinds of entry:
 *   - `approved: true`: Shipping, Returns, Privacy and Terms, which are
 *     GOLD_COAST_TOKOTA.md §8 to §11, the client's own policy text, kept to its
 *     wording. Ranges read "to" rather than a dash, as everywhere on the site.
 *     Change them only when that document changes (its §22: don't invent a
 *     policy, don't change one).
 *   - everything else is placeholder that nobody at Gold Coast Tokota or any
 *     lawyer has reviewed. It exists to give each page its shape, and every
 *     page that renders it also renders `<ContentDraftNotice />` saying so.
 *     Do not mark it approved to "tidy up" a page; see `usePageContent`, which
 *     is the single place the draft decision is made.
 *
 * The phone number is never written here (§22.11 says it is not final); point
 * to WhatsApp, whose number comes from site settings.
 */

/** A paragraph, or a bulleted list. Rendered as real nodes, never `v-html`. */
export type PolicyBlock = string | { list: string[] }

export type PolicySection = {
  heading: string
  body: PolicyBlock[]
}

export type PolicyDraft = {
  slug: string
  title: string
  /** One-line deck under the title. */
  summary: string
  /** ISO date the copy was written or last approved, not "today". */
  updated: string
  /** The client's own text (GOLD_COAST_TOKOTA.md), shown without the draft banner. */
  approved?: boolean
  sections: PolicySection[]
}

// Do Not Sell, Supply Chain Transparency and Vendor Code of Conduct were
// removed for launch on 28 Oct 2026: US-style pages the brand reference never
// mentions, with placeholder text nobody had approved. Their URLs now 404.
export const LEGAL_SLUGS = ['privacy', 'terms'] as const

export const HELP_SLUGS = ['returns', 'shipping', 'bulk-orders'] as const

export type LegalSlug = (typeof LEGAL_SLUGS)[number]
export type HelpSlug = (typeof HELP_SLUGS)[number]

const DRAFTED = '2026-08-26'
/** When §8 to §11 of GOLD_COAST_TOKOTA.md went onto the site. */
const FROM_BRAND_REFERENCE = '2026-10-28'

const HANDMADE_VARIATION = { list: ['Colour', 'Texture', 'Finish'] }

export const POLICY_DRAFTS: Record<string, PolicyDraft> = {
  // GOLD_COAST_TOKOTA.md §10. The one addition is naming who does the work the
  // policy describes: Paystack takes payments (§13) and Vercel Web Analytics,
  // which sets no cookies, counts visits.
  privacy: {
    slug: 'privacy',
    title: 'Privacy Policy',
    summary: 'What we collect when you shop with us, how we use it, and how we protect it.',
    updated: FROM_BRAND_REFERENCE,
    approved: true,
    sections: [
      {
        heading: 'Information collected',
        body: [
          'Gold Coast Tokota may collect:',
          { list: ['Name', 'Email address', 'Phone number', 'Shipping address', 'Billing information', 'Order history', 'Website usage data'] },
        ],
      },
      {
        heading: 'How information is used',
        body: [
          'Customer information is used to:',
          { list: ['Process and deliver orders', 'Respond to enquiries', 'Improve customer experience', 'Send order updates', 'Provide marketing communications with consent'] },
        ],
      },
      {
        heading: 'Payment security',
        body: [
          'Gold Coast Tokota does not store payment card information.',
          'Payments are securely processed through trusted third-party payment providers. Our payment provider is Paystack.',
        ],
      },
      {
        heading: 'Data protection',
        body: [
          'Appropriate technical and organisational measures are implemented to protect information from:',
          { list: ['Unauthorised access', 'Misuse', 'Disclosure'] },
        ],
      },
      {
        heading: 'Marketing communications',
        body: [
          'Customers may unsubscribe from promotional emails at any time by:',
          { list: ['Clicking the unsubscribe link', 'Contacting Gold Coast Tokota directly'] },
        ],
      },
      {
        heading: 'Cookies',
        body: [
          'The website uses cookies to:',
          { list: ['Improve website functionality', 'Remember preferences', 'Analyse website traffic', 'Enhance user experience'] },
          'Users can disable cookies through their browser settings.',
          'Website traffic is measured with Vercel Web Analytics, which does not use cookies.',
        ],
      },
    ],
  },

  // GOLD_COAST_TOKOTA.md §11.
  terms: {
    slug: 'terms',
    title: 'Terms of Service',
    summary: 'The terms you agree to when you use the Gold Coast Tokota website.',
    updated: FROM_BRAND_REFERENCE,
    approved: true,
    sections: [
      {
        heading: 'Acceptance',
        body: ['By accessing or using the Gold Coast Tokota website, customers agree to the Terms of Service.'],
      },
      {
        heading: 'Products',
        body: [
          'All products are handcrafted.',
          'Minor differences in:',
          HANDMADE_VARIATION,
          'are natural characteristics of handmade products.',
        ],
      },
      {
        heading: 'Pricing',
        body: [
          { list: ['All prices are displayed in Ghana Cedis (GHS) unless otherwise stated.', 'Prices may change without prior notice.'] },
        ],
      },
      {
        heading: 'Orders',
        body: [
          'Gold Coast Tokota reserves the right to:',
          { list: ['Refuse fraudulent orders', 'Cancel orders due to pricing errors', 'Limit quantities purchased'] },
          'Customers will be notified if an order cannot be fulfilled.',
        ],
      },
      {
        heading: 'Intellectual property',
        body: [
          'All website content is the intellectual property of Gold Coast Tokota.',
          'This includes:',
          { list: ['Logos', 'Product designs', 'Images', 'Videos', 'Graphics', 'Text', 'Brand assets'] },
          'Content may not be reproduced, copied or used without prior written permission.',
        ],
      },
      {
        heading: 'Product availability',
        body: [
          'Products are subject to availability.',
          'If an item becomes unavailable after purchase, customers will be offered:',
          { list: ['A replacement', 'Store credit', 'A full refund'] },
        ],
      },
      {
        heading: 'Limitation of liability',
        body: [
          'Gold Coast Tokota shall not be liable for delays or damages resulting from events beyond its reasonable control, including:',
          { list: ['Courier disruptions', 'Customs delays', 'Natural disasters', 'Other force majeure events'] },
        ],
      },
      {
        heading: 'Governing law',
        body: [
          'These Terms of Service are governed by the laws of the Republic of Ghana.',
          'Disputes arising from use of the website are subject to the jurisdiction of the courts of Ghana.',
        ],
      },
    ],
  },

  // GOLD_COAST_TOKOTA.md §9. Its contact line gives a WhatsApp number; that
  // number lives in site settings, so the page points to WhatsApp instead.
  returns: {
    slug: 'returns',
    title: 'Returns & Exchanges',
    summary: 'When we accept returns and exchanges, and how refunds work.',
    updated: FROM_BRAND_REFERENCE,
    approved: true,
    sections: [
      {
        heading: 'Returns',
        body: [
          'Customer satisfaction is important to Gold Coast Tokota.',
          'Returns are accepted within 7 days of receiving the order if:',
          { list: ['The product is defective.', 'The incorrect item was delivered.', 'The product arrived damaged during shipping.'] },
        ],
      },
      {
        heading: 'Returned product requirements',
        body: [
          'Returned products must:',
          { list: ['Be unused.', 'Be in their original condition.', 'Include original packaging.'] },
        ],
      },
      {
        heading: 'Exchanges',
        body: [
          'Exchanges are available for:',
          { list: ['Incorrect size, subject to stock availability', 'Manufacturing defects', 'Incorrect item received'] },
          'Customers requesting a size exchange are responsible for return shipping unless the error was made by Gold Coast Tokota.',
        ],
      },
      {
        heading: 'Non-returnable items',
        body: [
          'The following cannot be returned:',
          { list: ['Custom-made sandals', 'Personalised products', 'Products damaged through misuse', 'Clearance or sale items, unless defective'] },
        ],
      },
      {
        heading: 'Refunds',
        body: [
          'Approved refunds are processed within 7 to 14 business days.',
          'Refunds use the original payment method.',
          'Shipping charges are non-refundable unless the error was caused by Gold Coast Tokota.',
        ],
      },
      {
        heading: 'Return and exchange contact',
        body: ['Message us on WhatsApp with your order number to start a return or exchange.'],
      },
    ],
  },

  // GOLD_COAST_TOKOTA.md §8. The international table is a list here.
  shipping: {
    slug: 'shipping',
    title: 'Shipping & Delivery',
    summary: 'Where we deliver, how long it takes, and how it is priced.',
    updated: FROM_BRAND_REFERENCE,
    approved: true,
    sections: [
      {
        heading: 'Shipping locations',
        body: ['Gold Coast Tokota ships handcrafted sustainable footwear and accessories across Ghana and to selected international destinations.'],
      },
      {
        heading: 'Domestic shipping: Ghana',
        body: [
          {
            list: [
              'Orders are processed within 48 hours after payment confirmation.',
              'Standard delivery takes 1 to 2 business days, depending on destination.',
              'Delivery is available nationwide through trusted courier partners.',
              'Customers receive a confirmation message and tracking information where available once their order has been dispatched.',
            ],
          },
        ],
      },
      {
        heading: 'International shipping',
        body: [
          'Gold Coast Tokota ships worldwide, subject to courier availability and customs regulations.',
          'Processing time: 48 hours.',
          'Estimated delivery:',
          {
            list: [
              'West Africa: 5 to 10 business days',
              'Europe: 7 to 14 business days',
              'North America: 7 to 14 business days',
              'Other destinations: 10 to 21 business days',
            ],
          },
          'Delivery times may vary because of:',
          { list: ['Customs clearance', 'Public holidays', 'Courier delays'] },
        ],
      },
      {
        heading: 'Shipping costs',
        body: [
          'Shipping charges are calculated at checkout based on:',
          { list: ['Delivery destination', 'Package weight', 'Courier rates'] },
          'Any customs duties, taxes or import fees imposed by the destination country are the responsibility of the customer.',
        ],
      },
      {
        heading: 'Handmade products',
        body: [
          'Because products are handcrafted, slight variations in:',
          HANDMADE_VARIATION,
          'may occur.',
          'These variations are part of the uniqueness of each item and are not considered defects.',
        ],
      },
      {
        heading: 'Order tracking',
        body: [
          'Customers receive shipping updates via:',
          { list: ['Email', 'WhatsApp'] },
          'once orders have been dispatched.',
        ],
      },
    ],
  },

  'bulk-orders': {
    slug: 'bulk-orders',
    title: 'Bulk & Corporate Orders',
    summary: 'Larger orders for events, gifting, teams and stockists.',
    updated: DRAFTED,
    sections: [
      {
        heading: 'What we can do',
        body: [
          'We take bulk orders for weddings, corporate gifting, hotel and hospitality use, and wholesale for stockists.',
          'Bulk pairs can be made in a single colourway, and we can add branding to packaging.',
        ],
      },
      {
        heading: 'Lead times',
        body: [
          'Bulk orders are made to order, so please come to us early. Larger runs need more notice than a single pair. We will confirm a date in writing before you commit.',
        ],
      },
      {
        heading: 'Getting a quote',
        body: [
          'Tell us the quantity, the style, the sizes and the date you need them by, and we will come back with a price. WhatsApp is the fastest way to reach us.',
        ],
      },
    ],
  },

  accessibility: {
    slug: 'accessibility',
    title: 'Accessibility',
    summary: 'How we are working to keep this site usable for everyone.',
    updated: DRAFTED,
    sections: [
      {
        heading: 'Our aim',
        body: [
          'We are working towards the Web Content Accessibility Guidelines (WCAG) 2.1 at level AA.',
          'That means text that stays readable when enlarged, controls that work from a keyboard, visible focus outlines, and images that carry meaningful descriptions.',
        ],
      },
      {
        heading: 'Where we know we fall short',
        body: [
          'This is an honest work in progress rather than a finished claim. Some product photography still lacks full descriptions, and some longer pages have not yet been tested with a screen reader end to end.',
        ],
      },
      {
        heading: 'Telling us about a problem',
        body: [
          'If something on this site is hard to use, please tell us. It is the fastest way for us to find and fix it. Contact us or message us on WhatsApp.',
        ],
      },
    ],
  },
}

/**
 * Drives both `/help` and the help articles, so the hub and its pages cannot
 * drift apart. `icon` names a Phosphor icon exported by `@phosphor-icons/vue`.
 */
export const HELP_TOPICS: {
  slug: HelpSlug
  title: string
  summary: string
}[] = HELP_SLUGS.map((slug) => ({
  slug,
  title: POLICY_DRAFTS[slug]!.title,
  summary: POLICY_DRAFTS[slug]!.summary,
}))

/** Formats a policy `updated` date the way `newsPosts.formatPostDate` does. */
export function formatPolicyDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}
