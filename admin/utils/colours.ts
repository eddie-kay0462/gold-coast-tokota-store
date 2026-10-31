/**
 * Named colours for the product colour picker.
 *
 * The first ten are the catalogue's own, at the hexes its products already
 * use, so a new product's "Tan" matches the existing ones on the storefront's
 * swatches. The rest are common footwear colours, so typing one sets a
 * sensible swatch.
 */
export interface NamedColour {
  name: string
  hex: string
}

export const CATALOGUE_COLOURS: NamedColour[] = [
  { name: 'Black', hex: '#000000' },
  { name: 'White', hex: '#FFFFFF' },
  { name: 'Brown', hex: '#5A3A22' },
  { name: 'Tan', hex: '#B8732E' },
  { name: 'Red', hex: '#C6212B' },
  { name: 'Navy', hex: '#1B2A4A' },
  { name: 'Blue', hex: '#1E4C8F' },
  { name: 'Green', hex: '#2F4A3A' },
  { name: 'Purple', hex: '#3B1F5B' },
  { name: 'Grey', hex: '#A39D91' },
]

export const NAMED_COLOURS: NamedColour[] = [
  ...CATALOGUE_COLOURS,
  { name: 'Charcoal', hex: '#36454F' },
  { name: 'Beige', hex: '#D9C7A7' },
  { name: 'Cream', hex: '#F3E9D2' },
  { name: 'Camel', hex: '#C19A6B' },
  { name: 'Cognac', hex: '#9A463D' },
  { name: 'Maroon', hex: '#6B1E28' },
  { name: 'Burgundy', hex: '#800020' },
  { name: 'Olive', hex: '#6B6B2F' },
  { name: 'Khaki', hex: '#BDB38A' },
  { name: 'Gold', hex: '#C9A13B' },
  { name: 'Silver', hex: '#C0C0C0' },
  { name: 'Orange', hex: '#E0702A' },
  { name: 'Yellow', hex: '#E8C32E' },
  { name: 'Pink', hex: '#E59AB0' },
]

/** The swatch for a colour name, ignoring case and spacing. Null if unknown. */
export function hexFor(name: string): string | null {
  const key = name.trim().toLowerCase().replace(/\s+/g, ' ')
  if (!key) return null
  const alias: Record<string, string> = { gray: 'grey', 'navy blue': 'navy', 'off white': 'cream', 'off-white': 'cream' }
  const wanted = alias[key] ?? key
  return NAMED_COLOURS.find((c) => c.name.toLowerCase() === wanted)?.hex ?? null
}

function rgb(hex: string): [number, number, number] | null {
  const m = hex.trim().match(/^#?([0-9a-f]{6})$/i)
  if (!m) return null
  const n = parseInt(m[1], 16)
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255]
}

/**
 * The nearest named colour to a swatch, or null when nothing is close. Uses a
 * weighted RGB distance ("redmean"), which tracks how different two colours
 * look far better than plain RGB does, and is plenty for naming a swatch.
 */
export function nameFor(hex: string): string | null {
  const a = rgb(hex)
  if (!a) return null
  let best: { name: string; d: number } | null = null
  for (const c of NAMED_COLOURS) {
    const b = rgb(c.hex)!
    const r = (a[0] + b[0]) / 2
    const [dr, dg, db] = [a[0] - b[0], a[1] - b[1], a[2] - b[2]]
    const d = Math.sqrt((2 + r / 256) * dr * dr + 4 * dg * dg + (2 + (255 - r) / 256) * db * db)
    if (!best || d < best.d) best = { name: c.name, d }
  }
  return best && best.d < 120 ? best.name : null
}
