/**
 * Responsive regression check.
 *
 * Loads every storefront route at each supported viewport width and asserts the
 * three things that are objectively checkable without a human eye:
 *
 *   1. the document does not scroll horizontally;
 *   2. no element's box extends past the right edge of the viewport;
 *   3. at touch widths, interactive controls meet the 44px tap-target floor.
 *
 * It is a diagnostic, not a snapshot suite — "no overflow" is not the same as
 * "reads well", so the tablet and desktop widths still need looking at.
 *
 * Usage:
 *   npm run check:responsive                  # starts `nuxt preview` itself
 *   BASE_URL=http://localhost:3000 npm run check:responsive   # use a running server
 *
 * Requires a production build (`npm run build`) unless BASE_URL is given.
 */
import { spawn } from 'node:child_process'
import process from 'node:process'
import { chromium } from 'playwright-core'

const WIDTHS = [320, 375, 414, 768, 834, 1024, 1280, 1440, 1920, 2560]

/** Widths we treat as touch devices for the tap-target rule. */
const TOUCH_MAX_WIDTH = 768

/** Minimum tap target, WCAG 2.5.5 / iOS HIG. */
const TAP_MIN = 44

// Slugs come from the design fallbacks in utils/, so these resolve with or
// without the Laravel API running.
const ALL_ROUTES = [
  '/',
  '/about',
  '/sustainability',
  '/blog',
  '/blog/celebrating-au-day',
  '/shop',
  '/shop/kentehene-collection',
  '/checkout',
  '/booking',
  // Account. Inert, but the shell's base -> md rail is the layout most likely
  // to overflow, so every page that uses it is covered.
  '/account',
  '/account/login',
  '/account/register',
  '/account/orders',
  '/account/settings',
  // Prose templates: one representative each, since the template is what
  // overflows, not the individual slug.
  '/legal/privacy',
  '/legal/terms',
  '/help',
  '/help/returns',
  '/accessibility',
  // Bespoke marketing and commerce. `/size-guide` is the one to watch — it
  // carries a wide table, which is the classic 320px overflow.
  '/careers',
  '/international',
  '/affiliates',
  '/stores',
  '/gift-cards',
  '/size-guide',
  '/contact',
  '/community/submit',
]

/**
 * `ROUTES=/size-guide,/help npm run check:responsive` narrows the sweep while
 * iterating on one page. The full list is the default, and is what CI should
 * run — at 28 routes x 10 widths this takes a few minutes.
 */
const ROUTES = process.env.ROUTES
  ? process.env.ROUTES.split(',').map((route) => route.trim()).filter(Boolean)
  : ALL_ROUTES

const BASE_URL = process.env.BASE_URL
const PORT = 3000

/** Elements that legitimately sit outside the viewport or are visually hidden. */
const IGNORE_SELECTOR = [
  '[aria-hidden="true"]',
  '.sr-only',
  '[data-allow-overflow]',
].join(',')

const inPageAudit = ({ tapMin, isTouch, ignoreSelector }) => {
  const results = { overflow: [], taps: [] }

  const describe = (el) => {
    const id = el.id ? `#${el.id}` : ''
    const cls = typeof el.className === 'string' && el.className
      ? `.${el.className.trim().split(/\s+/).slice(0, 3).join('.')}`
      : ''
    return `${el.tagName.toLowerCase()}${id}${cls}`
  }

  const viewportWidth = document.documentElement.clientWidth
  const ignored = new Set(document.querySelectorAll(ignoreSelector))

  /**
   * True when some ancestor clips or scrolls the x-axis. Such an element is
   * contained by that ancestor and cannot push the document sideways — a
   * marquee track inside `overflow-hidden`, or a snap rail inside
   * `overflow-x-auto`, is doing exactly what it was built to do.
   */
  const isContained = (el) => {
    for (let node = el.parentElement; node && node !== document.body; node = node.parentElement) {
      if (getComputedStyle(node).overflowX !== 'visible') return true
    }
    return false
  }

  for (const el of document.querySelectorAll('body *')) {
    if (ignored.has(el)) continue
    // SVG internals inherit their extent from the <svg> box; reporting every
    // <path> just duplicates whatever the icon's own finding already says.
    if (el.ownerSVGElement) continue

    const style = getComputedStyle(el)
    if (style.visibility === 'hidden' || style.display === 'none') continue

    const rect = el.getBoundingClientRect()
    if (rect.width === 0 && rect.height === 0) continue

    // A fixed/sticky element deliberately parked off-screen (a closed drawer)
    // does not make the document scroll, so it is not an overflow.
    const parked = (style.position === 'fixed' || style.position === 'sticky')
      && rect.left >= viewportWidth

    if (!parked && !isContained(el) && rect.right > viewportWidth + 1) {
      results.overflow.push({
        selector: describe(el),
        right: Math.round(rect.right),
        overBy: Math.round(rect.right - viewportWidth),
      })
    }

    if (isTouch && el.matches('a[href], button, input:not([type="hidden"]), select, [role="tab"], [role="button"]')) {
      // Skip controls inside a closed disclosure — they are not reachable yet.
      if (rect.width === 0 || rect.height === 0) continue
      // WCAG 2.5.5 exempts a target that sits inline in a sentence or block of
      // text — padding prose links to 44px would wreck the line rhythm.
      if (style.display === 'inline') continue
      // An input wrapped in a label is activated by the whole label, so the
      // label's box is the real target.
      const label = el.closest('label')
      if (label && label !== el) {
        const lr = label.getBoundingClientRect()
        if (Math.min(lr.width, lr.height) >= tapMin) continue
      }
      const short = Math.min(rect.width, rect.height)
      if (short < tapMin) {
        results.taps.push({
          selector: describe(el),
          size: `${Math.round(rect.width)}×${Math.round(rect.height)}`,
        })
      }
    }
  }

  results.docOverflow = Math.max(
    0,
    document.documentElement.scrollWidth - viewportWidth,
  )

  // Report each distinct selector once — a grid of 20 identical cards is one bug.
  const dedupe = (list) => {
    const seen = new Map()
    for (const item of list) {
      if (!seen.has(item.selector)) seen.set(item.selector, { ...item, count: 1 })
      else seen.get(item.selector).count += 1
    }
    return [...seen.values()]
  }

  results.overflow = dedupe(results.overflow)
  results.taps = dedupe(results.taps)
  return results
}

async function waitForServer(url, timeoutMs = 90_000) {
  const deadline = Date.now() + timeoutMs
  while (Date.now() < deadline) {
    try {
      const response = await fetch(url, { redirect: 'manual' })
      if (response.status < 500) return true
    }
    catch {
      // not up yet
    }
    await new Promise((resolve) => setTimeout(resolve, 500))
  }
  return false
}

async function launchBrowser() {
  // Prefer the system Chrome so no browser download is needed; fall back to a
  // cached Playwright chromium if Chrome is not installed.
  try {
    return await chromium.launch({ channel: 'chrome' })
  }
  catch {
    return await chromium.launch()
  }
}

async function main() {
  let server = null
  let baseUrl = BASE_URL

  if (!baseUrl) {
    baseUrl = `http://localhost:${PORT}`
    server = spawn('npx', ['nuxt', 'preview', '--port', String(PORT)], {
      stdio: 'ignore',
      detached: false,
    })
    if (!(await waitForServer(baseUrl))) {
      server.kill()
      console.error('Preview server did not start. Run `npm run build` first.')
      process.exit(1)
    }
  }

  const browser = await launchBrowser()
  const failures = []

  try {
    for (const route of ROUTES) {
      const page = await browser.newPage()
      for (const width of WIDTHS) {
        await page.setViewportSize({ width, height: 900 })
        const response = await page.goto(`${baseUrl}${route}`, {
          waitUntil: 'networkidle',
        })

        if (response && response.status() >= 400) {
          failures.push({ route, width, kind: 'http', detail: `HTTP ${response.status()}` })
          continue
        }

        const result = await page.evaluate(inPageAudit, {
          tapMin: TAP_MIN,
          isTouch: width <= TOUCH_MAX_WIDTH,
          ignoreSelector: IGNORE_SELECTOR,
        })

        if (result.docOverflow > 1) {
          failures.push({
            route,
            width,
            kind: 'document',
            detail: `scrolls ${result.docOverflow}px sideways`,
          })
        }
        for (const item of result.overflow) {
          failures.push({
            route,
            width,
            kind: 'element',
            detail: `${item.selector} overflows by ${item.overBy}px${item.count > 1 ? ` (×${item.count})` : ''}`,
          })
        }
        for (const item of result.taps) {
          failures.push({
            route,
            width,
            kind: 'tap',
            detail: `${item.selector} is ${item.size}${item.count > 1 ? ` (×${item.count})` : ''}`,
          })
        }
      }
      await page.close()
      process.stdout.write('.')
    }
  }
  finally {
    await browser.close()
    server?.kill()
  }

  process.stdout.write('\n\n')

  if (!failures.length) {
    console.log('✔ No horizontal overflow and no sub-44px tap targets.')
    return
  }

  const byKind = { document: [], element: [], tap: [], http: [] }
  for (const failure of failures) byKind[failure.kind].push(failure)

  const heading = {
    http: 'Route errors',
    document: 'Document-level horizontal scroll',
    element: 'Elements past the right edge',
    tap: `Tap targets under ${TAP_MIN}px (widths ≤ ${TOUCH_MAX_WIDTH})`,
  }

  for (const kind of ['http', 'document', 'element', 'tap']) {
    if (!byKind[kind].length) continue
    console.log(`\n${heading[kind]} — ${byKind[kind].length}`)
    for (const { route, width, detail } of byKind[kind]) {
      console.log(`  ${route} @ ${width}px → ${detail}`)
    }
  }

  console.log(`\n${failures.length} finding(s).`)
  process.exit(1)
}

main().catch((error) => {
  console.error(error)
  process.exit(1)
})
