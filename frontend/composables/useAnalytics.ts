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
  }
}
