import type { ApiProduct } from '~/utils/catalog'

/**
 * The catalogue as drawn in Figma (listing node 10:2275, detail node 10:3394).
 *
 * Feature 2 owns the real `GET /api/v1/products` endpoints; until they exist,
 * both the listing and the product detail page fall back to this so the pages
 * render their designed state rather than an empty shell. Only the Acheampong
 * Collection is drawn in full on the detail frame, so it is the only entry with
 * description, review and cost-breakdown copy — the others intentionally omit
 * those fields, and the detail page hides the corresponding sections.
 */
export const DESIGN_PRODUCTS: ApiProduct[] = [
  {
    slug: 'kentehene-collection',
    name: 'The Kentehene Collection',
    base_price_ghs: 60000,
    compare_at_ghs: 70000,
    images: ['/design/product-kentehene.png'],
    color: 'Brown',
    colors: [
      { name: 'Brown', hex: '#8B5A2B' },
      { name: 'Navy', hex: '#1B2A4A' },
      { name: 'Tan', hex: '#C4AE93' },
      { name: 'Black', hex: '#000000' },
    ],
    product_type: 'ahenema',
    departments: ['mens'],
    sizes: ['39', '40', '41', '42', '43'],
    widths: ['m', 'l'],
    description_heading: 'Woven Heritage, Everyday Wear',
    description:
      'The Kentehene Collection pairs a hand-woven kente strap with a full-grain leather footbed '
      + 'that softens to the shape of your foot within a week of wear. Each strap is woven in '
      + 'Bonwire and cut to order, so no two pairs carry exactly the same pattern. The outsole is '
      + 'stitched rather than glued — it can be resoled by any cobbler instead of replaced.',
    model_note: 'Model is 5′11″, wearing a size 42',
    size_availability: { 39: 4, 40: 9, 41: 6, 42: 0, 43: 3 },
    rating: {
      average: 4.7,
      count: 3,
      distribution: { 5: 2, 4: 1, 3: 0, 2: 0, 1: 0 },
      fit: 3,
    },
    reviews: [
      {
        id: 'kentehene-kwabena',
        author: 'Kwabena Mensah',
        verified: true,
        rating: 5,
        title: 'The weave is the real thing',
        body:
          'I grew up around kente and this is properly woven, not printed. Wore them to a wedding '
          + 'and three people asked where I got them. The leather needed about a week to soften.',
        created_at: '2026-07-29',
        attributes: [
          { label: 'Height', value: '5’10” - 6’0”' },
          { label: 'Size Purchased', value: '42' },
          { label: 'Usual Size', value: '42' },
        ],
      },
      {
        id: 'kentehene-abena',
        author: 'Abena Owusu',
        verified: true,
        rating: 4,
        title: 'Beautiful, slightly stiff at first',
        body:
          'Bought these for my father. The finishing is excellent and the sole feels solid. '
          + 'Knocking off a star only because the strap rubbed for the first few days.',
        created_at: '2026-07-11',
        attributes: [
          { label: 'Size Purchased', value: '41' },
          { label: 'Usual Size', value: '41' },
        ],
      },
      {
        id: 'kentehene-anonymous',
        author: 'Anonymous',
        verified: false,
        rating: 5,
        title: 'Worth the price',
        body: 'Second pair. The first lasted two years of daily wear and only needed a resole.',
        created_at: '2026-06-02',
        attributes: [{ label: 'Size Purchased', value: '40' }],
      },
    ],
    cost_breakdown: [
      { label: 'Materials', amount_ghs: 4500, icon: '/design/icons/cost-materials.svg' },
      { label: 'Hardware', amount_ghs: 500, icon: '/design/icons/cost-hardware.svg' },
      { label: 'Labor', amount_ghs: 5000, icon: '/design/icons/cost-labor.svg' },
      { label: 'Duties', amount_ghs: 1000, icon: '/design/icons/cost-duties.svg' },
      { label: 'Transport', amount_ghs: 2000, icon: '/design/icons/cost-transport.svg' },
    ],
  },
  {
    slug: 'acheampong-collection',
    name: 'The Acheampong Collection',
    base_price_ghs: 70000,
    compare_at_ghs: 75000,
    images: [
      '/design/pdp-acheampong-1.png',
      '/design/pdp-acheampong-2.png',
      '/design/pdp-acheampong-3.png',
      '/design/product-acheampong.png',
    ],
    color: 'Navy Blue',
    colors: [
      { name: 'Navy Blue', hex: '#1B2A4A' },
      { name: 'Black', hex: '#000000' },
      { name: 'Grey', hex: '#D9D9D9' },
    ],
    tags: ['Custom Made'],
    product_type: 'slippers',
    departments: ['mens'],
    sizes: ['38', '39', '40', '41', '42', '43', '44', '45', '46'],
    widths: ['m'],
    description_heading: 'Calm, Elevated and Purposeful',
    description:
      'Meet your new every day sandal. The Acheampong Collection has all the classic sandal '
      + 'detailing — strong soles, comfortable cushioning with a strap, and a good hem. The straps '
      + 'are fully lined for added comfort and it’s made with a GRS-certified recycled Italian wool '
      + 'and GRS-certified recycled nylon blend. Think cozy, comfy, and oh-so easy to wear. With the '
      + 'goal of increasing the use of recycled materials and reducing the harmful impacts of '
      + 'production, the Global Recycled Standard (GRS) sets requirements for third-party '
      + 'certification of recycled input in products — including chain of custody, social and '
      + 'environmental practices, and chemical restrictions.',
    model_note: 'Model is 6′2″, wearing a size 43',
    // Figma draws 41 and 46 at 50% opacity — the design's out-of-stock state.
    size_availability: {
      38: 6, 39: 4, 40: 5, 41: 0, 42: 8, 43: 7, 44: 3, 45: 2, 46: 0,
    },
    rating: {
      average: 5,
      count: 2,
      distribution: { 5: 2, 4: 0, 3: 0, 2: 0, 1: 0 },
      fit: 4,
    },
    reviews: [
      {
        id: 'ama-gyamfi',
        author: 'Ama Gyamfi',
        verified: true,
        rating: 5,
        title: 'Warm and very attractive on',
        body:
          'Got this to keep my husband comfortable on those long market days. He loves it as it '
          + 'not only is well made but he looks good in it and he knows it.',
        created_at: '2026-08-04',
        attributes: [
          { label: 'Height', value: '5’9” - 5’11”' },
          { label: 'Weight (lbs)', value: '161 - 180 lb' },
          { label: 'Body Type', value: 'Petite' },
          { label: 'Size Purchased', value: '45' },
          { label: 'Usual Size', value: '45' },
        ],
      },
      {
        id: 'anonymous',
        author: 'Anonymous',
        verified: true,
        rating: 5,
        title: 'Super comfy',
        body:
          'Great quality and super comfy. Got the 43 because I have a wide foot and it fits '
          + 'perfect. It does run a bit oversized which is good.',
        created_at: '2026-08-04',
        attributes: [
          { label: 'Height', value: '5’9” - 5’11”' },
          { label: 'Weight (lbs)', value: '161 - 180 lb' },
          { label: 'Body Type', value: 'Petite' },
          { label: 'Size Purchased', value: '43' },
          { label: 'Usual Size', value: '43' },
        ],
      },
    ],
    cost_breakdown: [
      { label: 'Materials', amount_ghs: 5000, icon: '/design/icons/cost-materials.svg' },
      { label: 'Hardware', amount_ghs: 500, icon: '/design/icons/cost-hardware.svg' },
      { label: 'Labor', amount_ghs: 6000, icon: '/design/icons/cost-labor.svg' },
      { label: 'Duties', amount_ghs: 1000, icon: '/design/icons/cost-duties.svg' },
      { label: 'Transport', amount_ghs: 2500, icon: '/design/icons/cost-transport.svg' },
    ],
  },
  {
    slug: 'adinkra-slippers',
    name: 'The Adinkra Slippers',
    base_price_ghs: 45000,
    compare_at_ghs: 55000,
    images: ['/design/product-adinkra.png'],
    color: 'Black and White',
    colors: [
      { name: 'White', hex: '#FFFFFF' },
      { name: 'Olive', hex: '#5A6134' },
    ],
    tags: ['Renewed Materials', 'Cleaner Chemistry'],
    product_type: 'slippers',
    departments: ['mens', 'womens'],
    sizes: ['38', '39', '40', '41', '42'],
    widths: ['s', 'm'],
    description_heading: 'Symbols That Carry Meaning',
    description:
      'Each pair of Adinkra Slippers is stamped by hand with a single symbol — Gye Nyame, Sankofa '
      + 'or Dwennimmen — using dye pressed from badie bark, the same method used for adinkra cloth. '
      + 'The upper is offcut leather recovered from our sandal production, which is why the shade '
      + 'varies slightly between pairs. Built flat and unlined for warm weather.',
    model_note: 'Model is 5′8″, wearing a size 40',
    size_availability: { 38: 7, 39: 5, 40: 2, 41: 0, 42: 4 },
    rating: {
      average: 4.5,
      count: 4,
      distribution: { 5: 3, 4: 0, 3: 1, 2: 0, 1: 0 },
      fit: 2,
    },
    reviews: [
      {
        id: 'adinkra-efua',
        author: 'Efua Boateng',
        verified: true,
        rating: 5,
        title: 'Light enough to live in',
        body:
          'I wear these around the house and to the shop and forget I have them on. The stamped '
          + 'symbol has not faded at all after two months.',
        created_at: '2026-08-06',
        attributes: [
          { label: 'Size Purchased', value: '38' },
          { label: 'Usual Size', value: '39' },
        ],
      },
      {
        id: 'adinkra-yaw',
        author: 'Yaw Darko',
        verified: true,
        rating: 3,
        title: 'Size up',
        body:
          'Quality is good but they run small — my usual 41 was tight across the toes. Exchanged '
          + 'for a 42 and those are right.',
        created_at: '2026-07-22',
        attributes: [
          { label: 'Size Purchased', value: '42' },
          { label: 'Usual Size', value: '41' },
        ],
      },
      {
        id: 'adinkra-nana',
        author: 'Nana Adjei',
        verified: true,
        rating: 5,
        title: 'Bought three pairs',
        body: 'One for me, two as gifts. Everyone asked about the symbol on the strap.',
        created_at: '2026-06-30',
        attributes: [{ label: 'Size Purchased', value: '40' }],
      },
      {
        id: 'adinkra-anonymous',
        author: 'Anonymous',
        verified: false,
        rating: 5,
        title: 'Comfortable straight away',
        body: 'No break-in period at all, unlike the leather sandals I bought elsewhere.',
        created_at: '2026-05-18',
        attributes: [{ label: 'Size Purchased', value: '39' }],
      },
    ],
    cost_breakdown: [
      { label: 'Materials', amount_ghs: 3500, icon: '/design/icons/cost-materials.svg' },
      { label: 'Hardware', amount_ghs: 400, icon: '/design/icons/cost-hardware.svg' },
      { label: 'Labor', amount_ghs: 4000, icon: '/design/icons/cost-labor.svg' },
      { label: 'Duties', amount_ghs: 700, icon: '/design/icons/cost-duties.svg' },
      { label: 'Transport', amount_ghs: 1400, icon: '/design/icons/cost-transport.svg' },
    ],
  },
  {
    slug: 'elevated-odeneho',
    name: 'The Elevated Odeneho',
    base_price_ghs: 50000,
    compare_at_ghs: 60000,
    images: ['/design/product-elevated-odeneho.png'],
    color: 'Brown',
    colors: [
      { name: 'Brown', hex: '#8B5A2B' },
      { name: 'Navy', hex: '#1B2A4A' },
      { name: 'Tan', hex: '#C4AE93' },
      { name: 'Black', hex: '#000000' },
    ],
    product_type: 'sandals',
    departments: ['mens'],
    sizes: ['41', '42', '43', '44', '45'],
    widths: ['m', 'l'],
    description_heading: 'A Little More Height, All Day Comfort',
    description:
      'The Elevated Odeneho takes our flat sandal and adds a 30mm stacked leather midsole with a '
      + 'cork footbed underneath. The cork moulds to your arch over the first month and stays '
      + 'there. Woven crossover straps distribute pressure so nothing digs in when you are standing '
      + 'for long stretches. Made to be resoled, not replaced.',
    model_note: 'Model is 6′0″, wearing a size 43',
    size_availability: { 41: 3, 42: 6, 43: 5, 44: 0, 45: 1 },
    rating: {
      average: 4.8,
      count: 5,
      distribution: { 5: 4, 4: 1, 3: 0, 2: 0, 1: 0 },
      fit: 3,
    },
    reviews: [
      {
        id: 'elevated-kofi',
        author: 'Kofi Asante',
        verified: true,
        rating: 5,
        title: 'On my feet twelve hours a day',
        body:
          'I teach and I am standing most of the day. These are the first sandals that have not '
          + 'left my heels aching. The cork really does mould to your foot.',
        created_at: '2026-08-10',
        attributes: [
          { label: 'Height', value: '5’11” - 6’1”' },
          { label: 'Size Purchased', value: '43' },
          { label: 'Usual Size', value: '43' },
        ],
      },
      {
        id: 'elevated-akosua',
        author: 'Akosua Frimpong',
        verified: true,
        rating: 5,
        title: 'Height without the wobble',
        body: 'Stable on uneven ground, which I did not expect from a raised sole.',
        created_at: '2026-07-27',
        attributes: [{ label: 'Size Purchased', value: '41' }],
      },
      {
        id: 'elevated-samuel',
        author: 'Samuel Ofori',
        verified: true,
        rating: 4,
        title: 'Great, but heavier than expected',
        body:
          'No complaints about build quality. Just be aware the stacked sole adds real weight '
          + 'compared to the flat version.',
        created_at: '2026-07-02',
        attributes: [
          { label: 'Size Purchased', value: '45' },
          { label: 'Usual Size', value: '44' },
        ],
      },
    ],
    cost_breakdown: [
      { label: 'Materials', amount_ghs: 3800, icon: '/design/icons/cost-materials.svg' },
      { label: 'Hardware', amount_ghs: 500, icon: '/design/icons/cost-hardware.svg' },
      { label: 'Labor', amount_ghs: 4400, icon: '/design/icons/cost-labor.svg' },
      { label: 'Duties', amount_ghs: 800, icon: '/design/icons/cost-duties.svg' },
      { label: 'Transport', amount_ghs: 1500, icon: '/design/icons/cost-transport.svg' },
    ],
  },
  {
    slug: 'flavourful-cross-slippers',
    name: 'Flavourful Cross Slippers',
    base_price_ghs: 45000,
    compare_at_ghs: 50000,
    images: ['/design/product-flavourful-cross.png'],
    color: 'Crystal Blue',
    colors: [
      { name: 'Crystal Blue', hex: '#1E4C8F' },
      { name: 'Black', hex: '#000000' },
      { name: 'Grey', hex: '#D9D9D9' },
    ],
    tags: ['Organic Materials'],
    product_type: 'slippers',
    departments: ['mens', 'womens'],
    sizes: ['38', '39', '40', '41'],
    widths: ['s', 'm'],
    description_heading: 'Dyed by Hand, No Two Alike',
    description:
      'The crossover strap is dyed in small batches using indigo grown in the Volta Region, which '
      + 'is why the blue shifts between pairs and deepens with sun exposure. Underneath is an '
      + 'organic cotton canvas footbed over natural latex — no synthetic foam anywhere in the '
      + 'build. Best kept out of standing water, since the dye is unfixed by design.',
    model_note: 'Model is 5′7″, wearing a size 39',
    size_availability: { 38: 5, 39: 8, 40: 0, 41: 2 },
    rating: {
      average: 4.3,
      count: 3,
      distribution: { 5: 1, 4: 2, 3: 0, 2: 0, 1: 0 },
      fit: 4,
    },
    reviews: [
      {
        id: 'flavourful-adwoa',
        author: 'Adwoa Sarpong',
        verified: true,
        rating: 4,
        title: 'The colour is the whole point',
        body:
          'Mine came out a deeper blue than the photo and I like it more for that. Fair warning: '
          + 'the dye did transfer slightly onto light socks the first week.',
        created_at: '2026-08-12',
        attributes: [
          { label: 'Size Purchased', value: '39' },
          { label: 'Usual Size', value: '39' },
        ],
      },
      {
        id: 'flavourful-emmanuel',
        author: 'Emmanuel Tetteh',
        verified: true,
        rating: 5,
        title: 'Softest footbed I own',
        body: 'The latex under the canvas makes a real difference. Runs a touch large.',
        created_at: '2026-07-19',
        attributes: [
          { label: 'Size Purchased', value: '41' },
          { label: 'Usual Size', value: '42' },
        ],
      },
      {
        id: 'flavourful-anonymous',
        author: 'Anonymous',
        verified: false,
        rating: 4,
        title: 'Good for the price',
        body: 'Comfortable and well finished. Would have liked more colour options.',
        created_at: '2026-06-21',
        attributes: [{ label: 'Size Purchased', value: '38' }],
      },
    ],
    cost_breakdown: [
      { label: 'Materials', amount_ghs: 3400, icon: '/design/icons/cost-materials.svg' },
      { label: 'Hardware', amount_ghs: 400, icon: '/design/icons/cost-hardware.svg' },
      { label: 'Labor', amount_ghs: 3800, icon: '/design/icons/cost-labor.svg' },
      { label: 'Duties', amount_ghs: 600, icon: '/design/icons/cost-duties.svg' },
      { label: 'Transport', amount_ghs: 1300, icon: '/design/icons/cost-transport.svg' },
    ],
  },
  {
    slug: 'odeneho-collection',
    name: 'The Odeneho Collection',
    base_price_ghs: 25000,
    compare_at_ghs: 30000,
    images: ['/design/product-odeneho.png'],
    color: 'Olive Green',
    colors: [{ name: 'Olive Green', hex: '#5A6134' }],
    tags: ['Renewed Materials', 'Cleaner Chemistry'],
    is_pre_order: true,
    product_type: 'closed-toe',
    departments: ['mens'],
    sizes: ['42', '43', '44', '45'],
    widths: ['m'],
    description_heading: 'The Original, Made From What We Save',
    description:
      'The sandal we started with. The olive upper is cut from leather offcuts recovered off our '
      + 'own cutting floor, and the footbed is bonded with a water-based adhesive rather than a '
      + 'solvent one. Simple flat build, closed toe, nothing on it that cannot be repaired. '
      + 'Currently made to order — pre-order pairs ship within three weeks.',
    model_note: 'Model is 5′9″, wearing a size 43',
    size_availability: { 42: 0, 43: 0, 44: 0, 45: 0 },
    rating: {
      average: 4.6,
      count: 5,
      distribution: { 5: 3, 4: 2, 3: 0, 2: 0, 1: 0 },
      fit: 3,
    },
    reviews: [
      {
        id: 'odeneho-grace',
        author: 'Grace Amankwah',
        verified: true,
        rating: 5,
        title: 'Three years on the same pair',
        body:
          'Bought these when the shop opened. Resoled once, otherwise untouched. Ordering a second '
          + 'pair now that they are back.',
        created_at: '2026-08-14',
        attributes: [
          { label: 'Size Purchased', value: '43' },
          { label: 'Usual Size', value: '43' },
        ],
      },
      {
        id: 'odeneho-ibrahim',
        author: 'Ibrahim Alhassan',
        verified: true,
        rating: 4,
        title: 'Worth the wait',
        body:
          'Pre-ordered and it took just under three weeks as stated. Closed toe is good for the '
          + 'dust in harmattan.',
        created_at: '2026-07-08',
        attributes: [
          { label: 'Size Purchased', value: '45' },
          { label: 'Usual Size', value: '45' },
        ],
      },
      {
        id: 'odeneho-anonymous',
        author: 'Anonymous',
        verified: true,
        rating: 5,
        title: 'Best value in the shop',
        body: 'Cheapest pair they make and the build is the same as the expensive ones.',
        created_at: '2026-05-30',
        attributes: [{ label: 'Size Purchased', value: '42' }],
      },
    ],
    cost_breakdown: [
      { label: 'Materials', amount_ghs: 2000, icon: '/design/icons/cost-materials.svg' },
      { label: 'Hardware', amount_ghs: 200, icon: '/design/icons/cost-hardware.svg' },
      { label: 'Labor', amount_ghs: 2200, icon: '/design/icons/cost-labor.svg' },
      { label: 'Duties', amount_ghs: 400, icon: '/design/icons/cost-duties.svg' },
      { label: 'Transport', amount_ghs: 700, icon: '/design/icons/cost-transport.svg' },
    ],
  },
]

