/**
 * Placeholder copy for the policy, help and accessibility pages.
 *
 * The same design-fallback pattern as `designCatalogue.ts` and `newsPosts.ts`:
 * each page fetches `GET /pages/{slug}` and falls back to the draft here when
 * the CMS has nothing approved. Feature 9 owns the real content — when the
 * owner publishes a page from admin, `is_draft` flips to false and the entry
 * below stops rendering. Delete an entry once its page is live.
 *
 * IMPORTANT: none of this text has been reviewed by Gold Coast Tokota or by a
 * lawyer. It exists to give each page its shape and structure, and every page
 * that renders it also renders `<ContentDraftNotice />` saying exactly that.
 * Do not remove that banner to "tidy up" a page — see `usePageContent`, which
 * is the single place the draft decision is made.
 */

export type PolicySection = {
  heading: string
  /** Paragraphs. Rendered as real nodes, never `v-html`. */
  body: string[]
}

export type PolicyDraft = {
  slug: string
  title: string
  /** One-line deck under the title. */
  summary: string
  /** ISO date. Deliberately the date the draft was written, not "today". */
  updated: string
  sections: PolicySection[]
}

export const LEGAL_SLUGS = [
  'privacy',
  'terms',
  'do-not-sell',
  'supply-chain',
  'vendor-code',
] as const

export const HELP_SLUGS = ['returns', 'shipping', 'bulk-orders'] as const

export type LegalSlug = (typeof LEGAL_SLUGS)[number]
export type HelpSlug = (typeof HELP_SLUGS)[number]

const DRAFTED = '2026-08-26'

export const POLICY_DRAFTS: Record<string, PolicyDraft> = {
  privacy: {
    slug: 'privacy',
    title: 'Privacy Policy',
    summary: 'What we collect when you shop with us, why we hold it, and how to ask us to delete it.',
    updated: DRAFTED,
    sections: [
      {
        heading: 'What we collect',
        body: [
          'When you place an order we collect your name, email address, phone number and delivery address. When you book a workshop or a custom DIY pair we also collect the measurements and reference images you send us.',
          'We do not store your card details. Payments are handled by Paystack (for cedi payments) and Stripe (for dollar payments), and your card information goes to them directly.',
        ],
      },
      {
        heading: 'Why we hold it',
        body: [
          'To make and deliver what you ordered, to send you the confirmation email and SMS, to answer you when you contact us, and to keep the records the law requires us to keep.',
          'We do not sell your personal information. See our Do Not Sell page for more.',
        ],
      },
      {
        heading: 'Who we share it with',
        body: [
          'Our delivery partners — Yango within Ghana, DHL internationally — receive the address and phone number needed to deliver your order, and nothing more.',
          'Our SMS provider receives your phone number in order to send order and booking confirmations.',
        ],
      },
      {
        heading: 'Your rights under Act 843',
        body: [
          'Ghana’s Data Protection Act, 2012 (Act 843) gives you the right to ask what personal data we hold about you, to have it corrected, and to ask us to delete it.',
          'To make any of these requests, contact us and we will respond within the period the Act allows.',
        ],
      },
      {
        heading: 'Cookies',
        body: [
          'We use a small number of first-party cookies to remember your cart and your chosen currency between visits. We use Google Analytics to understand how the site is used.',
        ],
      },
    ],
  },

  terms: {
    slug: 'terms',
    title: 'Terms of Service',
    summary: 'The terms you agree to when you buy from or book with Gold Coast Tokota.',
    updated: DRAFTED,
    sections: [
      {
        heading: 'Orders',
        body: [
          'An order is confirmed when payment has cleared and you have received a confirmation email. Until then we may cancel an order — for example if an item turns out to be out of stock — and refund you in full.',
          'Every pair is handmade, so slight variation in grain, colour and finish between pairs is expected and is not a defect.',
        ],
      },
      {
        heading: 'Pricing and currency',
        body: [
          'All prices are set in Ghana cedis. Dollar prices are converted from the cedi price using the exchange rate at the time you view the page.',
          'For dollar orders the rate is locked when you begin payment, so the amount you are charged matches the amount you were shown.',
        ],
      },
      {
        heading: 'Custom and DIY orders',
        body: [
          'Custom pairs are made to the measurements you supply. Please check them carefully — a custom pair made correctly to the measurements given cannot be returned for fit.',
          'Turnaround times shown at the time of booking are estimates, not guarantees.',
        ],
      },
      {
        heading: 'Workshops',
        body: [
          'Workshop places are limited. If a session is full you may join the waitlist and we will contact you if a place opens up.',
        ],
      },
      {
        heading: 'Governing law',
        body: [
          'These terms are governed by the laws of the Republic of Ghana.',
        ],
      },
    ],
  },

  'do-not-sell': {
    slug: 'do-not-sell',
    title: 'Do Not Sell or Share My Personal Information',
    summary: 'We do not sell your personal information. Here is what that means in practice.',
    updated: DRAFTED,
    sections: [
      {
        heading: 'We do not sell your data',
        body: [
          'Gold Coast Tokota does not sell your personal information, and does not share it for cross-context behavioural advertising.',
          'This page exists because some privacy laws require us to say so plainly, and to give you a way to ask.',
        ],
      },
      {
        heading: 'What we do share',
        body: [
          'We pass your delivery details to the courier carrying your order, and your phone number to the provider sending your confirmation SMS. Both act on our instructions and cannot use your details for anything else.',
        ],
      },
      {
        heading: 'Making a request',
        body: [
          'To ask what we hold, to correct it, or to have it deleted, contact us with the email address you used to order. We may ask you to confirm your identity before we act.',
        ],
      },
    ],
  },

  'supply-chain': {
    slug: 'supply-chain',
    title: 'Supply Chain Transparency',
    summary: 'Where our materials come from and how we check the conditions they are made in.',
    updated: DRAFTED,
    sections: [
      {
        heading: 'Our workshop',
        body: [
          'Our sandals are made in our own workshop in Ghana by artisans we employ directly, not through a labour agency.',
        ],
      },
      {
        heading: 'Materials',
        body: [
          'We source leather, woven kente trim and soling materials from suppliers in Ghana wherever possible, and we visit them.',
          'Where a material has to be imported, we say so on the product page.',
        ],
      },
      {
        heading: 'Forced and child labour',
        body: [
          'We do not tolerate forced labour or child labour anywhere in our supply chain. Any supplier found using either is dropped.',
          'Our expectations of suppliers are set out in the Vendor Code of Conduct.',
        ],
      },
      {
        heading: 'Reporting a concern',
        body: [
          'If you believe any part of our supply chain falls short of this, contact us. Reports can be made anonymously.',
        ],
      },
    ],
  },

  'vendor-code': {
    slug: 'vendor-code',
    title: 'Vendor Code of Conduct',
    summary: 'What we require of every supplier and partner we work with.',
    updated: DRAFTED,
    sections: [
      {
        heading: 'Who this applies to',
        body: [
          'Every supplier, contractor and partner who provides materials or services to Gold Coast Tokota, including their own subcontractors.',
        ],
      },
      {
        heading: 'Labour',
        body: [
          'No forced labour. No child labour. Wages at or above the legal minimum, paid on time. Working hours within legal limits, with overtime voluntary and paid.',
          'Workers may organise and raise grievances without retaliation.',
        ],
      },
      {
        heading: 'Health and safety',
        body: [
          'A safe workplace, with the training and protective equipment the work requires, and clean drinking water and sanitation.',
        ],
      },
      {
        heading: 'Environment',
        body: [
          'Compliance with applicable environmental law, responsible handling of chemicals and waste, and a willingness to reduce waste over time.',
        ],
      },
      {
        heading: 'Monitoring',
        body: [
          'We visit our suppliers. We may ask to see records, and we may end a relationship where this code is not met and not corrected.',
        ],
      },
    ],
  },

  returns: {
    slug: 'returns',
    title: 'Returns & Exchanges',
    summary: 'How to return or exchange a pair, and what we can and cannot take back.',
    updated: DRAFTED,
    sections: [
      {
        heading: 'The window',
        body: [
          'Unworn pairs in their original packaging can be returned within 30 days of delivery. Holiday orders benefit from an extended window through January 31.',
        ],
      },
      {
        heading: 'What we cannot take back',
        body: [
          'Custom and DIY pairs made to your own measurements cannot be returned for fit, because they were made only for you. If a custom pair does not match the measurements you gave us, that is our error and we will put it right.',
          'Pairs that have been worn outdoors cannot be returned.',
        ],
      },
      {
        heading: 'Exchanges',
        body: [
          'For a different size in the same style, contact us before sending anything back so we can reserve the size for you.',
        ],
      },
      {
        heading: 'Starting a return',
        body: [
          'Message us on WhatsApp or use the contact form with your order number. We will confirm the return address and the next step.',
          'Refunds go back to the payment method used, in the currency charged.',
        ],
      },
    ],
  },

  shipping: {
    slug: 'shipping',
    title: 'Shipping & Delivery',
    summary: 'Where we deliver, how long it takes, and what it costs.',
    updated: DRAFTED,
    sections: [
      {
        heading: 'Within Ghana',
        body: [
          'Domestic orders are delivered by Yango. Accra deliveries typically arrive within one to two working days; elsewhere in Ghana, two to four.',
          'Delivery is free on Ghana orders over ₵1,500.',
        ],
      },
      {
        heading: 'International',
        body: [
          'International orders ship with DHL. Transit time depends on the destination and is shown at checkout before you pay.',
        ],
      },
      {
        heading: 'Duties and taxes',
        body: [
          'International customers are responsible for any import duties or taxes charged by their own country. These are not included in the price you pay us.',
        ],
      },
      {
        heading: 'Made-to-order timing',
        body: [
          'Custom and DIY pairs are made before they ship, so the making time shown when you order comes first, and delivery time is added to it.',
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
          'Bulk orders are made to order, so please come to us early — larger runs need more notice than a single pair. We will confirm a date in writing before you commit.',
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
          'If something on this site is hard to use, please tell us — it is the fastest way for us to find and fix it. Contact us or message us on WhatsApp.',
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
