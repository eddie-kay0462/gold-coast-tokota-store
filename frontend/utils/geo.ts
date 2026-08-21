/** Shared between the Nitro geo resolver and the storefront components. */

export type GeoSource = 'edge' | 'ip' | 'timezone' | 'fallback'
export type GeoConfidence = 'high' | 'medium' | 'low'

export interface GeoResult {
  /** ISO-3166-1 alpha-2, uppercase. */
  country: string
  /** Which signal actually decided the answer. */
  source: GeoSource
  confidence: GeoConfidence
  /** True when the connection looks like a VPN/proxy/datacenter exit. */
  vpnSuspected: boolean
}

/**
 * Flag SVG for a country code. Served by Nitro out of the flag-icons package
 * (see `nitro.publicAssets` in nuxt.config), so only the visitor's own flag is
 * ever fetched.
 */
export function flagUrl(country: string): string {
  return `/flags/${country.toLowerCase()}.svg`
}

/** Localised country name, falling back to the raw code where Intl can't. */
export function countryName(country: string, locale = 'en'): string {
  try {
    return new Intl.DisplayNames([locale], { type: 'region' }).of(country) ?? country
  }
  catch {
    return country
  }
}
