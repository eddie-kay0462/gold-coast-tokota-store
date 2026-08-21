import type { AdminUser, Customer, FeedbackEntry, NewsletterSubscriber } from '~/types'
import { avatarFor, chance, daysAgo, daysAhead, ghs, hoursAgo, int, pick, rand } from './_seed'

/**
 * The real team, from the brand PDF's "Admin and Staff User Roles" table.
 * Two interns are added to exercise the time-boxed access model — one active,
 * one already lapsed, because the lapsed state is the one that usually ships
 * broken.
 */
export const adminUsers: AdminUser[] = [
  {
    id: 1,
    name: 'Samuel Kumi-Gyau',
    email: 'samuel@goldcoasttokota.store',
    role: 'super_admin',
    jobTitle: 'Founder & CEO',
    avatar: avatarFor('Samuel Kumi-Gyau'),
    accessExpiresAt: null,
    accessExtensions: [],
    lastActiveAt: hoursAgo(1),
    createdAt: daysAgo(420),
  },
  {
    id: 2,
    name: 'Mary Seade',
    email: 'mary@goldcoasttokota.store',
    role: 'admin',
    jobTitle: 'Operations Lead',
    avatar: avatarFor('Mary Seade'),
    accessExpiresAt: null,
    accessExtensions: [],
    lastActiveAt: hoursAgo(3),
    createdAt: daysAgo(380),
  },
  {
    id: 3,
    name: 'Isaac Boateng',
    email: 'isaac@goldcoasttokota.store',
    role: 'admin',
    jobTitle: 'Finance & Administration Officer',
    avatar: avatarFor('Isaac Boateng'),
    accessExpiresAt: null,
    accessExtensions: [],
    lastActiveAt: daysAgo(1),
    createdAt: daysAgo(365),
  },
  {
    id: 4,
    name: 'Isaaka Mahama',
    email: 'isaaka@goldcoasttokota.store',
    role: 'staff',
    jobTitle: 'Production Lead',
    avatar: avatarFor('Isaaka Mahama'),
    accessExpiresAt: null,
    accessExtensions: [],
    lastActiveAt: hoursAgo(6),
    createdAt: daysAgo(300),
  },
  {
    id: 5,
    name: 'Peter Nyarko',
    email: 'peter@goldcoasttokota.store',
    role: 'staff',
    jobTitle: 'Operations & Logistics Assistant',
    avatar: avatarFor('Peter Nyarko'),
    accessExpiresAt: null,
    accessExtensions: [],
    lastActiveAt: hoursAgo(2),
    createdAt: daysAgo(240),
  },
  {
    id: 6,
    name: 'Akosua Danso',
    email: 'akosua.intern@goldcoasttokota.store',
    role: 'intern',
    jobTitle: 'Production Intern',
    avatar: avatarFor('Akosua Danso'),
    accessExpiresAt: daysAhead(12),
    accessExtensions: [
      {
        extendedAt: daysAgo(18),
        extendedByName: 'Mary Seade',
        previousExpiry: daysAgo(12),
        newExpiry: daysAhead(12),
        days: 30,
      },
    ],
    lastActiveAt: hoursAgo(5),
    createdAt: daysAgo(78),
  },
  {
    id: 7,
    name: 'Kwesi Appiah',
    email: 'kwesi.intern@goldcoasttokota.store',
    role: 'intern',
    jobTitle: 'Marketing Intern',
    avatar: avatarFor('Kwesi Appiah'),
    // Already lapsed — the read-only state has to be visible somewhere.
    accessExpiresAt: daysAgo(4),
    accessExtensions: [],
    lastActiveAt: daysAgo(5),
    createdAt: daysAgo(94),
  },
]

const firstNames = ['Ama', 'Kojo', 'Efua', 'Yaw', 'Adwoa', 'Kwabena', 'Abena', 'Kofi',
  'Sarah', 'Daniel', 'Grace', 'Marcus', 'Naa', 'Elorm', 'Selorm', 'Chiamaka',
  'Olivia', 'Thomas', 'Aisha', 'Nadia']
const lastNames = ['Mensah', 'Owusu', 'Asante', 'Addo', 'Quartey', 'Tetteh', 'Boadu',
  'Johnson', 'Williams', 'Okafor', 'Dubois', 'Andersson', 'Bello', 'Sarpong']
const places: [string, string][] = [
  ['Ghana', 'GH'], ['Ghana', 'GH'], ['Ghana', 'GH'], ['Ghana', 'GH'],
  ['United States', 'US'], ['United Kingdom', 'GB'], ['Canada', 'CA'],
  ['Germany', 'DE'], ['Nigeria', 'NG'], ['Netherlands', 'NL'],
]

export const customers: Customer[] = Array.from({ length: 48 }, (_, i) => {
  const name = `${pick(firstNames)} ${pick(lastNames)}`
  const [country, countryCode] = pick(places)
  const orderCount = int(0, 9)
  return {
    id: 100 + i,
    name,
    email: `${name.toLowerCase().replace(/\s+/g, '.')}${i}@example.com`,
    phone: countryCode === 'GH' ? `+2332${int(10, 59)}${int(100000, 999999)}` : null,
    preferredCurrency: countryCode === 'GH' ? 'GHS' : 'USD',
    hasAccount: chance(0.55),
    country,
    countryCode,
    orderCount,
    lifetimeValue: ghs(orderCount * int(12000, 68000)),
    lastOrderAt: orderCount ? daysAgo(int(0, 120)) : null,
    createdAt: daysAgo(int(5, 400)),
  }
})

const sources = ['Footer form', 'Checkout opt-in', 'Blog post', 'Instagram bio', 'Workshop signup']

export const newsletterSubscribers: NewsletterSubscriber[] = Array.from(
  { length: 64 },
  (_, i) => {
    const name = `${pick(firstNames)}${pick(lastNames)}`.toLowerCase()
    return {
      id: 500 + i,
      email: `${name}${i}@example.com`,
      source: pick(sources),
      subscribedAt: daysAgo(int(0, 180), int(0, 20)),
    }
  },
)

const feedbackMessages = [
  'The Kente-strap sandals are beautiful. Sizing ran a little large but the finish is superb.',
  'Sip & Paint workshop was the highlight of our trip to Accra. Please run more weekend slots.',
  'Delivery to London took eleven days, a bit longer than quoted, but the packaging was lovely.',
  'Would love to see wider fittings — I am a size 45 and the standard last is narrow.',
  'Bought three pairs as gifts. Everyone asked where they came from. The story card is a nice touch.',
  'The recycled tyre sole is genuinely comfortable. Sceptical at first, converted now.',
  'Customer service on WhatsApp was quick and warm. Sorted my exchange in a day.',
  'Please add a size guide with foot-length measurements in cm to the product page.',
]

export const feedbackEntries: FeedbackEntry[] = feedbackMessages.map((message, i) => {
  const name = `${pick(firstNames)} ${pick(lastNames)}`
  return {
    id: 700 + i,
    name,
    email: `${name.toLowerCase().replace(/\s+/g, '.')}@example.com`,
    rating: chance(0.85) ? int(3, 5) : null,
    message,
    submittedAt: daysAgo(int(0, 60), int(0, 20)),
  }
})
