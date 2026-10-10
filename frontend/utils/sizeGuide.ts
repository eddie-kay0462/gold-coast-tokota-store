/**
 * The size guide's content, shared by the `/size-guide` page and the size guide
 * modal on the product page, so the two can never disagree.
 *
 * NOTE: the conversions below are the standard EU/UK/US ladder, not measured
 * Gold Coast Tokota lasts. Confirm against real production lasts before launch:
 * a size chart that is wrong by half a size causes returns.
 */
export const SIZE_GUIDE_ROWS = [
  { eu: '38', uk: '5', us: '6', cm: '24.0' },
  { eu: '39', uk: '6', us: '7', cm: '24.7' },
  { eu: '40', uk: '6.5', us: '7.5', cm: '25.3' },
  { eu: '41', uk: '7.5', us: '8.5', cm: '26.0' },
  { eu: '42', uk: '8', us: '9', cm: '26.7' },
  { eu: '43', uk: '9', us: '10', cm: '27.3' },
  { eu: '44', uk: '9.5', us: '10.5', cm: '28.0' },
  { eu: '45', uk: '10.5', us: '11.5', cm: '28.7' },
  { eu: '46', uk: '11', us: '12', cm: '29.3' },
]

export const SIZE_GUIDE_STEPS = [
  'Stand on a sheet of paper with your heel against a wall.',
  'Mark the paper at the tip of your longest toe.',
  'Measure from the wall edge to the mark, in centimetres.',
  'Measure late in the day. Feet swell, so an evening measurement is the honest one.',
  'If your feet differ, use the larger.',
]

export const SIZE_GUIDE_BETWEEN_SIZES =
  'Between sizes? Take the larger. An ahenema should sit with a little room at the toe.'
