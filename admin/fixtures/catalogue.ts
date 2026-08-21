import type { Category, FxRate, InventoryItem, Product } from '~/types'
import { chance, daysAgo, ghs, hoursAgo, int, pick } from './_seed'

export const categories: Category[] = [
  { id: 1, name: 'Henema Sandals', slug: 'henema-sandals', productCount: 9 },
  { id: 2, name: 'Kente Collection', slug: 'kente-collection', productCount: 5 },
  { id: 3, name: 'Recycled Tyre Soles', slug: 'recycled-tyre-soles', productCount: 4 },
  { id: 4, name: 'Accessories', slug: 'accessories', productCount: 4 },
  { id: 5, name: 'DIY Kits', slug: 'diy-kits', productCount: 2 },
]

/**
 * Products reflect what the brand actually makes: handcrafted henema sandals
 * from recycled tyre and textile waste, per the PDF's mission statement.
 * Prices are GHS minor units (pesewas) — USD is derived, never stored.
 */
const catalogue: [string, number, number, boolean][] = [
  // name, category id, price in pesewas, featured
  ['Ahenema Classic — Tan', 1, 38000, true],
  ['Ahenema Classic — Black', 1, 38000, false],
  ['Ahenema Slim Strap', 1, 34500, false],
  ['Adinkra Embossed Slide', 1, 42000, true],
  ['Coastal Two-Strap', 1, 36000, false],
  ['Heritage Wedge', 1, 52000, false],
  ['Elder Ahenema — Wide Fit', 1, 45000, false],
  ['Market Day Flat', 1, 29500, false],
  ['Sunday Best Ahenema', 1, 58000, true],
  ['Kente Panel Slide', 2, 49500, true],
  ['Kente Weave Thong', 2, 44000, false],
  ['Bonwire Strap Sandal', 2, 61000, false],
  ['Kente Trim Mule', 2, 47500, false],
  ['Kente Cuff Sandal', 2, 53000, false],
  ['Retread Trail Sandal', 3, 41000, true],
  ['Retread Everyday', 3, 35000, false],
  ['Retread Sport Strap', 3, 39500, false],
  ['Retread Kids', 3, 26000, false],
  ['Woven Card Holder', 4, 14000, false],
  ['Leather Offcut Keyring', 4, 6500, false],
  ['Sandal Care Kit', 4, 9500, false],
  ['Canvas Tote — Offcut Panel', 4, 22000, false],
  ['DIY Sandal Kit — Adult', 5, 32000, true],
  ['DIY Sandal Kit — Youth', 5, 27000, false],
]

export const products: Product[] = catalogue.map(([name, categoryId, price, featured], i) => {
  const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
  const available = chance(0.12) ? int(0, 3) : int(6, 90)
  return {
    id: 1000 + i,
    name,
    slug,
    sku: `GCT-${String(1000 + i)}`,
    description:
      'Handcrafted in Accra from reclaimed materials. Because every pair is made by hand, ' +
      'slight variations in colour, texture and finish are part of its character.',
    categoryId,
    categoryName: categories.find((c) => c.id === categoryId)!.name,
    basePriceGhs: ghs(price),
    images: [],
    isActive: chance(0.88),
    isFeatured: featured,
    totalAvailable: available,
    totalReserved: chance(0.3) ? int(1, 4) : 0,
    lowStock: available <= 5,
    createdAt: daysAgo(int(30, 400)),
    updatedAt: daysAgo(int(0, 30)),
  }
})

const sizes = ['38', '39', '40', '41', '42', '43', '44', '45']
const colourways = ['Tan', 'Black', 'Ochre', 'Indigo', 'Natural']

export const inventoryItems: InventoryItem[] = products.flatMap((p, pi) =>
  Array.from({ length: 4 }, (_, vi) => {
    const available = chance(0.14) ? int(0, 2) : int(3, 26)
    const reserved = chance(0.25) ? int(1, 3) : 0
    return {
      id: 2000 + pi * 4 + vi,
      productId: p.id,
      productName: p.name,
      sku: `${p.sku}-${sizes[vi + 2]}`,
      variantAttributes: { size: sizes[vi + 2]!, colour: pick(colourways) },
      quantityAvailable: available,
      quantityReserved: reserved,
      reservationExpiresAt: reserved ? hoursAgo(-0.2) : null,
      lowStockThreshold: 5,
      updatedAt: daysAgo(int(0, 14)),
    }
  }),
)

/**
 * FX rate. README Feature 2 leaves the provider open (Clarifications Needed
 * item 2), so the fixture names the recommendation rather than inventing a
 * vendor relationship that does not exist.
 */
export const fxRate: FxRate = {
  baseCurrency: 'GHS',
  quoteCurrency: 'USD',
  rate: 0.0643,
  fetchedAt: hoursAgo(2),
  source: 'exchangerate.host (proposed — provider not yet selected)',
  isStale: false,
}
