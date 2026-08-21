import type { ChatMessage, ChatThread, MessageTemplate } from '~/types'
import { avatarFor, daysAgo, hoursAgo, minsAgo } from './_seed'
import { WHATSAPP_GREETING } from './settings'

/**
 * Simulated WhatsApp inbox.
 *
 * Every thread is a scenario the business actually handles — a size exchange
 * inside the 7-day returns window, a Sip & Paint capacity question, a bulk
 * corporate enquiry, a DIY order with measurements, an international shipping
 * timeline, a MoMo payment confirmation. Generic "sample message" filler would
 * make the screen look plausible and teach an operator nothing.
 *
 * NOTHING HERE IS LIVE. See types/messaging.ts for the scope note.
 */

type Draft = [inbound: boolean, body: string, minutesAgo: number, kind?: 'text' | 'document' | 'image' | 'system']

interface ThreadDraft {
  id: string
  name: string
  phone: string
  topic: ChatThread['topic']
  online: boolean
  unread: number
  linkedOrderId?: number
  linkedBookingId?: number
  messages: Draft[]
}

const drafts: ThreadDraft[] = [
  {
    id: 'wa-1', name: 'Adwoa Mensah', phone: '+233 24 118 4402', topic: 'returns',
    online: true, unread: 2,
    messages: [
      [true, 'Hi! I received my Ahenema Classic in tan yesterday and they’re gorgeous, but they run a little large.', 190],
      [true, 'Can I exchange for a 41?', 188],
      [false, WHATSAPP_GREETING, 187, 'system'],
      [false, 'Hello Adwoa, thank you for reaching out. Yes — exchanges for sizing are available within 7 days of delivery, subject to stock. May I have your order number?', 172],
      [true, 'GCT-8100685', 165],
      [false, 'Thank you. I can see a size 41 in tan is in stock. For a size exchange the return postage is the customer’s, but we’ll cover sending the new pair out. Shall I raise it?', 150],
      [true, 'Yes please, that works.', 22],
      [true, 'Do I need to include the original box?', 18],
    ],
  },
  {
    id: 'wa-2', name: 'Kwame Asare', phone: '+233 20 774 9931', topic: 'booking',
    online: true, unread: 1,
    messages: [
      [true, 'Good morning. Is the Sandal Sip & Paint fully booked for this Saturday?', 320],
      [false, 'Good morning Kwame. Saturday is at capacity — 20 of 20 confirmed. I can add you to the waitlist, and we’ll message you the moment a place opens.', 300],
      [true, 'Please do. There are two of us.', 290],
      [false, 'Added — you’re second on the list. The following Saturday has 6 places if you’d rather lock something in now.', 275],
      [true, 'Let’s hold the waitlist for this week and see.', 41],
    ],
  },
  {
    id: 'wa-3', name: 'Nadia Bello', phone: '+234 803 552 1180', topic: 'wholesale',
    online: false, unread: 0,
    messages: [
      [true, 'Hello — I run a concept store in Lagos. Interested in stocking 40 pairs of the Kente Panel Slide.', 2600],
      [false, 'Hello Nadia, thank you for getting in touch. Bulk orders of 20+ pairs run on a 1–3 week production window depending on quantity. For 40 pairs we’d quote 3 weeks.', 2580],
      [true, 'That works. Can you send a wholesale price list?', 2560],
      [false, 'Wholesale terms.pdf', 2540, 'document'],
      [true, 'Received, thank you. I’ll review with my partner this week.', 2400],
    ],
  },
  {
    id: 'wa-4', name: 'Sarah Whitfield', phone: '+44 7700 900412', topic: 'order',
    online: false, unread: 0, linkedOrderId: 3007,
    messages: [
      [true, 'Hi, I ordered last Tuesday to London. Any idea when it will arrive?', 1500],
      [false, 'Hello Sarah — processing takes 48 hours and Europe delivery runs 7–14 business days. Yours dispatched Thursday via DHL.', 1480],
      [false, 'Your tracking reference is DHL483920117.', 1478],
      [true, 'Perfect, thank you! It says it cleared customs this morning.', 900],
      [false, 'That’s the long part done. Should be with you in the next day or two.', 880],
    ],
  },
  {
    id: 'wa-5', name: 'Yaw Boadu', phone: '+233 55 302 7714', topic: 'diy_order',
    online: true, unread: 3,
    messages: [
      [true, 'I’d like to order a custom pair. Size 44 but wide across the front.', 130],
      [false, 'Happy to help. Custom sandal orders run 3–5 business days. Could you send a photo of your foot on a sheet of A4, heel to the edge? That gives us the length and width.', 120],
      [true, 'foot-measurement.jpg', 95, 'image'],
      [true, '28.5cm heel to toe.', 93],
      [true, 'Can I have the indigo strap with the recycled tyre sole?', 90],
    ],
  },
  {
    id: 'wa-6', name: 'Kofi Owusu', phone: '+233 27 441 6650', topic: 'order',
    online: false, unread: 0, linkedOrderId: 3012,
    messages: [
      [true, 'Just paid with MTN MoMo. Did it come through?', 640],
      [false, 'Yes — payment confirmed on order GCT-8100959. You’ll get an SMS and email receipt shortly, and we dispatch within 48 hours.', 630],
      [true, 'Great, thanks 👍', 620],
    ],
  },
  {
    id: 'wa-7', name: 'Grace Ampofo', phone: '+233 26 889 0031', topic: 'booking',
    online: false, unread: 0,
    messages: [
      [true, 'Hello, I teach at a school in East Legon. Could we bring a class of 35 for the sustainability tour?', 4200],
      [false, 'Hello Grace — school tours run Monday to Friday and take up to 40 students, so 35 is fine. Morning slot is 9:00 AM – 12:00 PM.', 4180],
      [true, 'Could we do the second Tuesday of next month?', 4150],
      [false, 'Booked provisionally. I’ll confirm once we’ve allocated a guide.', 4100],
    ],
  },
  {
    id: 'wa-8', name: 'Marcus Dubois', phone: '+1 415 555 0177', topic: 'general',
    online: false, unread: 0,
    messages: [
      [true, 'Do you ship to the US? And are the soles really made from tyres?', 5400],
      [false, 'We do — North America runs 7–14 business days. And yes: the soles are cut from reclaimed tyre rubber, which is why they wear so well.', 5380],
      [true, 'Amazing. Ordering a pair now.', 5300],
    ],
  },
  {
    id: 'wa-9', name: 'Efua Quartey', phone: '+233 24 660 2218', topic: 'returns',
    online: false, unread: 0,
    messages: [
      [true, 'The stitching on the toe post has come loose after two weeks.', 8800],
      [false, 'I’m sorry to hear that — that’s a defect and covered. Send a photo and we’ll arrange a repair or replacement at no cost.', 8780],
      [true, 'defect-photo.jpg', 8700, 'image'],
      [false, 'Thank you. Replacement pair is going out today.', 8600],
      [true, 'Received and they’re perfect. Thank you for sorting it so quickly.', 7000],
    ],
  },
  {
    id: 'wa-10', name: 'Thomas Andersson', phone: '+31 6 2044 8813', topic: 'general',
    online: false, unread: 0,
    messages: [
      [true, 'Visiting Accra in October — can I book the international visitor experience?', 12000],
      [false, 'Absolutely. It runs by appointment, 2–4 hours, up to 20 people. Let us know your dates when you have them.', 11900],
    ],
  },
]

const messages: ChatMessage[] = []
export const chatThreads: ChatThread[] = drafts.map((d) => {
  d.messages.forEach(([inbound, body, mins, kind], i) => {
    messages.push({
      id: `${d.id}-m${i}`,
      threadId: d.id,
      direction: inbound ? 'inbound' : 'outbound',
      kind: kind ?? 'text',
      body: kind === 'image' || kind === 'document' ? '' : body,
      attachment: kind === 'image' || kind === 'document'
        ? { filename: body, url: '', mimeType: kind === 'image' ? 'image/jpeg' : 'application/pdf' }
        : null,
      status: inbound ? 'read' : 'read',
      simulated: false,
      sentAt: minsAgo(mins),
    })
  })
  const last = d.messages[d.messages.length - 1]!
  const lastInboundMins = [...d.messages].reverse().find((m) => m[0])?.[2] ?? 0
  return {
    id: d.id,
    contactName: d.name,
    contactPhone: d.phone,
    avatar: avatarFor(d.name),
    topic: d.topic,
    unreadCount: d.unread,
    isOnline: d.online,
    lastSeenAt: d.online ? minsAgo(0) : hoursAgo(Math.max(1, Math.round(lastInboundMins / 60))),
    lastMessagePreview: last[3] === 'image' || last[3] === 'document' ? `📎 ${last[1]}` : last[1],
    lastMessageAt: minsAgo(last[2]),
    // Cloud API rule: free-form replies are only permitted for 24 hours after
    // the customer's last message. Past that you must use an approved template.
    sessionExpiresAt: minsAgo(lastInboundMins - 1440),
    linkedOrderId: d.linkedOrderId ?? null,
    linkedBookingId: d.linkedBookingId ?? null,
    assignedToAdminId: null,
    isArchived: false,
  }
})

export const chatMessages: ChatMessage[] = messages

/** Modelled on real WhatsApp Business template lifecycle states. */
export const messageTemplates: MessageTemplate[] = [
  {
    id: 'tpl-order-confirmation', name: 'order_confirmation', category: 'utility',
    language: 'en', status: 'approved',
    body: 'Hi {{1}}, your Gold Coast Tokota order {{2}} is confirmed. Total {{3}}. We dispatch within 48 hours.',
    variables: ['customer_name', 'order_reference', 'order_total'],
    rejectionReason: null, updatedAt: daysAgo(60),
  },
  {
    id: 'tpl-dispatch', name: 'order_dispatched', category: 'utility',
    language: 'en', status: 'approved',
    body: 'Good news {{1}} — order {{2}} has been dispatched via {{3}}. Tracking: {{4}}.',
    variables: ['customer_name', 'order_reference', 'courier', 'tracking_reference'],
    rejectionReason: null, updatedAt: daysAgo(60),
  },
  {
    id: 'tpl-booking-confirmation', name: 'booking_confirmation', category: 'utility',
    language: 'en', status: 'approved',
    body: 'Hi {{1}}, your {{2}} booking on {{3}} at {{4}} is confirmed. See you at the workshop in Haatso, Accra.',
    variables: ['customer_name', 'workshop_name', 'date', 'slot'],
    rejectionReason: null, updatedAt: daysAgo(45),
  },
  {
    id: 'tpl-waitlist', name: 'waitlist_promoted', category: 'utility',
    language: 'en', status: 'pending',
    body: 'A place has opened on {{1}} for {{2}}. Reply YES within 24 hours to claim it.',
    variables: ['workshop_name', 'date'],
    rejectionReason: null, updatedAt: daysAgo(3),
  },
  {
    id: 'tpl-restock', name: 'back_in_stock', category: 'marketing',
    language: 'en', status: 'rejected',
    body: '{{1}} is back in stock! Shop now before it goes again.',
    variables: ['product_name'],
    rejectionReason: 'Marketing templates must include an opt-out instruction.',
    updatedAt: daysAgo(9),
  },
]
