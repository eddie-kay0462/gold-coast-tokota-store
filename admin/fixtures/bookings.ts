import type {
  Booking, BookingStatus, DiyTurnaroundTier, WorkshopSession, WorkshopType,
} from '~/types'
import { customers } from './people'
import { chance, daysAgo, daysAhead, int, NOW, pick, ymd } from './_seed'

/**
 * The six workshops the business actually runs, transcribed from the brand
 * PDF's "Workshop Schedule" table — days, slot, duration and capacity are all
 * theirs, not invented.
 */
export const workshopTypes: WorkshopType[] = [
  {
    id: 1,
    name: 'Sandal Sip & Paint',
    slug: 'sandal-sip-and-paint',
    daysLabel: 'Every Saturday',
    slotLabel: '10:00 AM – 1:00 PM',
    durationLabel: '3 hours',
    capacity: 20,
    description:
      'Paint and finish your own pair over drinks. The most popular weekend session, ' +
      'and the one that fills first.',
    isActive: true,
  },
  {
    id: 2,
    name: 'Be a Shoemaker for a Day',
    slug: 'be-a-shoemaker-for-a-day',
    daysLabel: 'Every Friday',
    slotLabel: '9:00 AM – 4:00 PM',
    durationLabel: 'Full day',
    capacity: 10,
    description:
      'A full day at the bench with the production team, cutting, lasting and finishing ' +
      'a pair end to end.',
    isActive: true,
  },
  {
    id: 3,
    name: 'School Sustainability Tours',
    slug: 'school-sustainability-tours',
    daysLabel: 'Monday – Friday',
    slotLabel: '9:00 AM – 12:00 PM / 1:00 PM – 3:00 PM',
    durationLabel: '2–3 hours',
    capacity: 40,
    description:
      'Guided workshop tour for school groups covering circular manufacturing and how ' +
      'discarded tyres and textiles become footwear.',
    isActive: true,
  },
  {
    id: 4,
    name: 'Corporate Team Building Workshop',
    slug: 'corporate-team-building',
    daysLabel: 'By appointment',
    slotLabel: 'Flexible',
    durationLabel: 'Half day / full day',
    capacity: 30,
    description: 'Team session built around a shared make, scoped to the group and the day.',
    isActive: true,
  },
  {
    id: 5,
    name: 'Cultural Craft Experience',
    slug: 'cultural-craft-experience',
    daysLabel: 'By appointment',
    slotLabel: 'Flexible',
    durationLabel: '2 hours',
    capacity: 15,
    description: 'A shorter introduction to Ghanaian craft technique and its heritage.',
    isActive: true,
  },
  {
    id: 6,
    name: 'International Visitor Experience',
    slug: 'international-visitor-experience',
    daysLabel: 'By appointment',
    slotLabel: 'Flexible',
    durationLabel: '2–4 hours',
    capacity: 20,
    description: 'Extended visit for travellers, combining the workshop tour with a hands-on make.',
    isActive: true,
  },
]

/**
 * Sessions are generated across a window either side of "now" so the calendar
 * has both history and forward bookings, honouring each type's real recurrence:
 * Sip & Paint on Saturdays, Shoemaker on Fridays, school tours on weekdays.
 */
function sessionsFor(type: WorkshopType, weekday: number | null, count: number, startId: number): WorkshopSession[] {
  const out: WorkshopSession[] = []
  for (let d = -28; d <= 42 && out.length < count; d++) {
    const date = new Date(NOW.getTime() + d * 864e5)
    const dow = date.getUTCDay()
    if (weekday === null) {
      if (!chance(0.12) || dow === 0) continue
    } else if (dow !== weekday) continue

    // Skew the fill so roughly a quarter of sessions sell out. A capacity
    // model whose waitlist never triggers is a capacity model nobody has
    // tested — and README Feature 7 makes waitlist behaviour load-bearing.
    const confirmed = chance(0.26)
      ? type.capacity
      : int(Math.floor(type.capacity * 0.2), type.capacity - 1)
    out.push({
      id: startId + out.length,
      workshopTypeId: type.id,
      workshopTypeName: type.name,
      scheduledDate: ymd(date.toISOString()),
      scheduledSlot: type.slotLabel.split(' / ')[0]!,
      capacity: type.capacity,
      confirmedCount: confirmed,
      // A session at capacity carries a waitlist rather than blocking signup
      // (README Feature 7).
      waitlistCount: confirmed >= type.capacity ? int(1, 6) : 0,
      locationNotes: 'Gold Coast Tokota workshop, Haatso, Accra',
      createdByAdminId: 2,
      createdAt: daysAgo(int(20, 60)),
    })
  }
  return out
}

export const workshopSessions: WorkshopSession[] = [
  ...sessionsFor(workshopTypes[0]!, 6, 10, 6000),   // Saturdays
  ...sessionsFor(workshopTypes[1]!, 5, 10, 6100),   // Fridays
  ...sessionsFor(workshopTypes[2]!, null, 12, 6200),
  ...sessionsFor(workshopTypes[3]!, null, 4, 6300),
  ...sessionsFor(workshopTypes[4]!, null, 5, 6400),
  ...sessionsFor(workshopTypes[5]!, null, 6, 6500),
]

/** From the PDF's "DIY Sandal Order Turnaround Time" table. */
export const diyTurnaroundTiers: DiyTurnaroundTier[] = [
  { id: 'standard', label: 'Standard sandal order', estimate: '1–2 business days', sortOrder: 1 },
  { id: 'custom', label: 'Custom sandal order', estimate: '3–5 business days', sortOrder: 2 },
  { id: 'kit', label: 'DIY sandal kit', estimate: '1–2 business days', sortOrder: 3 },
  { id: 'bulk', label: 'Bulk orders (20+ pairs)', estimate: '1–3 weeks (depending on quantity)', sortOrder: 4 },
  { id: 'corporate', label: 'Corporate & event orders', estimate: '1–2 weeks (subject to project scope)', sortOrder: 5 },
]

const colourways = ['Tan / natural', 'Black / ochre', 'Indigo / natural', 'Ochre / black']
const soles = ['Recycled tyre', 'Reclaimed rubber', 'Layered offcut']

let bookingSeq = 0

const workshopBookings: Booking[] = workshopSessions
  .filter(() => chance(0.55))
  .slice(0, 34)
  .map((session) => {
    const c = pick(customers)
    const isFull = session.confirmedCount >= session.capacity
    const status: BookingStatus = isFull
      ? 'waitlisted'
      : pick(['pending', 'confirmed', 'confirmed', 'completed', 'cancelled'] as const)
    return {
      id: 7000 + bookingSeq++,
      reference: `WS-${4200 + bookingSeq}`,
      type: 'workshop' as const,
      status,
      customerName: c.name,
      customerEmail: c.email,
      customerPhone: c.phone ?? `+2332${int(10, 59)}${int(100000, 999999)}`,
      workshopSessionId: session.id,
      workshopTypeName: session.workshopTypeName,
      attendeeCount: int(1, 4),
      scheduledDate: null,
      diySpecs: null,
      waitlistPosition: status === 'waitlisted' ? int(1, 6) : null,
      whatsappThreadId: null,
      notes: '',
      createdAt: daysAgo(int(0, 30)),
    }
  })

/**
 * DIY orders are queue-based and never capacity-limited or rejected
 * (README Feature 7), so these are all accepted — only their stage varies.
 */
const diyBookings: Booking[] = Array.from({ length: 22 }, () => {
  const c = pick(customers)
  const tier = pick(diyTurnaroundTiers)
  return {
    id: 7000 + bookingSeq++,
    reference: `DIY-${5100 + bookingSeq}`,
    type: 'diy_order' as const,
    status: pick(['pending', 'confirmed', 'confirmed', 'completed'] as const),
    customerName: c.name,
    customerEmail: c.email,
    customerPhone: c.phone ?? `+2332${int(10, 59)}${int(100000, 999999)}`,
    workshopSessionId: null,
    workshopTypeName: null,
    attendeeCount: null,
    scheduledDate: daysAhead(int(2, 21)).slice(0, 10),
    diySpecs: {
      tierId: tier.id,
      size: String(int(38, 45)),
      footLengthCm: chance(0.7) ? int(240, 300) / 10 : null,
      colourway: pick(colourways),
      soleMaterial: pick(soles),
      referenceImages: [],
      preferredFulfilment: chance(0.6) ? 'delivery' : 'pickup',
      measurementsNote: chance(0.4) ? 'Slightly wider across the ball of the foot.' : '',
    },
    waitlistPosition: null,
    whatsappThreadId: null,
    notes: '',
    createdAt: daysAgo(int(0, 40)),
  }
})

export const bookings: Booking[] = [...workshopBookings, ...diyBookings]
