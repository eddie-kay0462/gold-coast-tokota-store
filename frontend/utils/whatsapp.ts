/**
 * Every WhatsApp message the storefront can send, in one place.
 *
 * README Feature 6 says the number and default message come from `SiteSetting`
 * "not hardcoded in multiple places". That was true of the *number* and false
 * of the *messages* — eleven of sixteen CTAs sent the same generic line, and
 * the ones that didn't each wrote their own string inline. The business opened
 * a chat from the cart, the stores page and an order enquiry and could not tell
 * them apart.
 *
 * The intents below map onto the five conversations the brand's own WhatsApp
 * auto-reply enumerates (brand guidelines, "Default Greeting Message"):
 * shop sandals · book a Sandal Sip & Paint · school or group tour ·
 * partnerships or bulk orders · sustainability. Arriving already in one of
 * those lanes is the whole point — it saves the first two messages of every
 * conversation.
 *
 * Keep the voice consistent: address the brand by name, say what you want, one
 * sentence. The brand guidelines call the customer-service tone "warm, helpful,
 * respectful, prompt".
 */

const BRAND = 'Gold Coast Tokota'

/** Where a WhatsApp click came from. Carried into GA4 as `whatsapp_click`. */
export type WhatsAppSource =
  | 'fab'
  | 'announcement'
  | 'footer'
  | 'product-card'
  | 'product-detail'
  | 'product-fit'
  | 'cart'
  | 'checkout'
  | 'order-tracking'
  | 'order-help'
  | 'returns'
  | 'shipping'
  | 'bulk-orders'
  | 'booking'
  | 'booking-appointment'
  | 'booking-waitlist'
  | 'booking-error'
  | 'diy-reference'
  | 'discount-code'
  | 'size-guide'
  | 'shop-empty'
  | 'stores'
  | 'gift-cards'
  | 'contact'
  | 'account'

const opening = `Hi ${BRAND},`

/**
 * Half the catalogue is named "The Kentehene Collection" and half "Flavourful
 * Cross Slippers", so a fixed "the " prefix produces "order the The Kentehene
 * Collection" for one and reads correctly for the other.
 */
function withArticle(name: string): string {
  return /^(the|a|an)\s/i.test(name) ? name : `the ${name}`
}

export const whatsappMessage = {
  /** The site-wide default: the floating button, the footer, the contact page. */
  general: () => `${opening} I'd like to know more about your sandals.`,

  /**
   * An order for one pair. The size clause appears only once a size has been
   * picked, so an unselected card does not claim a size the customer never chose.
   */
  product: (name: string, size?: string | null) =>
    `${opening} I'd like to order ${withArticle(name)}${size ? ` in size ${size}` : ''}.`,

  /** The whole basket, one line per item. */
  cart: (lines: string[], subtotal: string) =>
    [`${opening} I'd like to order:`, ...lines, `Subtotal ${subtotal}`].join('\n'),

  /** Brand guidelines: "shipping updates via email or WhatsApp once dispatched". */
  orderTracking: (reference: string) =>
    `${opening} could I get an update on order ${reference}?`,

  orderHelp: (reference?: string | null) =>
    reference
      ? `${opening} I have a question about order ${reference}.`
      : `${opening} I have a question about an order I placed.`,

  /** Brand guidelines: returns and exchanges are *initiated* over WhatsApp. */
  returns: (reference?: string | null) =>
    `${opening} I'd like to start a return or exchange${reference ? ` for order ${reference}` : ''}.`,

  shipping: () => `${opening} I have a question about delivery.`,

  /** The "partnerships or bulk orders" lane. */
  bulkOrder: () =>
    `${opening} I'd like to discuss a bulk or corporate order.`,

  /**
   * The workshop lane. Named when we know which one — three of the six
   * experiences in the brand guidelines are "by appointment" and have no
   * bookable session, so the name is all the business has to go on.
   */
  workshop: (name?: string | null) =>
    name
      ? `${opening} I'd like to arrange the ${name}.`
      : `${opening} I'd like to arrange a workshop booking.`,

  diyOrder: () => `${opening} I'd like to arrange a custom DIY sandal order.`,

  diyReference: (customerName?: string) =>
    `${opening} here is the reference photo for my DIY sandal order${customerName ? ` (${customerName})` : ''}.`,

  /** Sizing is the single most common pre-purchase question. */
  sizing: (product?: string | null) =>
    product
      ? `${opening} could you help me choose a size for the ${product}?`
      : `${opening} could you help me choose a size?`,

  /** Asked from an empty search, or a pair that is out of stock. */
  stockEnquiry: (product?: string | null) =>
    product
      ? `${opening} do you have the ${product} coming back in stock?`
      : `${opening} I couldn't find what I was looking for — can you help?`,

  /** The label on the stores page already promises directions. */
  visit: () => `${opening} could you send me directions to the workshop?`,

  giftEnquiry: () => `${opening} I'd like to arrange a gift.`,

  /** No discounts endpoint exists, so codes are applied by hand. */
  discountCode: (code?: string) =>
    `${opening} I have a discount code${code ? ` (${code})` : ''} — could you apply it to my order?`,
} as const
