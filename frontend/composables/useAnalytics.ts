// Mirrors GA4 e-commerce events client-side. Purchase events are additionally
// mirrored server-side (Laravel) so the admin dashboard never depends on GA4
// availability or ad-blockers — see Feature 11.
export function useAnalytics() {
  function trackEvent(name: string, params: Record<string, unknown> = {}) {
    if (typeof window === 'undefined') return
    const gtag = (window as unknown as { gtag?: (...args: unknown[]) => void }).gtag
    gtag?.('event', name, params)
  }

  return {
    viewItem: (params: Record<string, unknown>) => trackEvent('view_item', params),
    addToCart: (params: Record<string, unknown>) => trackEvent('add_to_cart', params),
    beginCheckout: (params: Record<string, unknown>) => trackEvent('begin_checkout', params),
    purchase: (params: Record<string, unknown>) => trackEvent('purchase', params),
    /**
     * Not one of Feature 11's four standard e-commerce events, and deliberately
     * added: WhatsApp is the only route that can actually complete an order
     * while payment is inert, and it was the one conversion path with no
     * measurement at all. The admin dashboard already renders a "WhatsApp"
     * traffic-channel tile — this is what can eventually feed it.
     *
     * `source` says which affordance was tapped (see `WhatsAppSource`), so the
     * brand can tell an order enquiry from a returns question.
     */
    whatsappClick: (params: { source: string } & Record<string, unknown>) =>
      trackEvent('whatsapp_click', params),
  }
}
