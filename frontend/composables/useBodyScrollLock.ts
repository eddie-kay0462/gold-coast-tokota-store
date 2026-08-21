/**
 * Locks background scrolling while an overlay is open.
 *
 * `document.body.style.overflow = 'hidden'` alone does not stop iOS Safari
 * scrolling the page behind an overlay — exactly the small screens where a
 * drawer or sheet is full-bleed. The reliable treatment is to take the body out
 * of flow at its current offset and put it back afterwards, which this does.
 *
 * Locks are reference-counted, so two overlays open at once (a modal above the
 * cart drawer) cannot unlock each other on close.
 *
 * Call `lock()`/`unlock()`, or pass a ref/getter to have it follow that state.
 */
let depth = 0
let savedScrollY = 0
let savedStyles: Partial<CSSStyleDeclaration> | null = null

function applyLock() {
  const { body } = document
  savedScrollY = window.scrollY
  savedStyles = {
    position: body.style.position,
    top: body.style.top,
    left: body.style.left,
    right: body.style.right,
    width: body.style.width,
    overflow: body.style.overflow,
  }
  body.style.position = 'fixed'
  body.style.top = `-${savedScrollY}px`
  body.style.left = '0'
  body.style.right = '0'
  body.style.width = '100%'
  body.style.overflow = 'hidden'
}

function releaseLock() {
  const { body } = document
  if (savedStyles) Object.assign(body.style, savedStyles)
  savedStyles = null
  // Restoring position also restores the scroll offset the browser had before.
  window.scrollTo(0, savedScrollY)
}

export function useBodyScrollLock(source?: MaybeRefOrGetter<boolean>) {
  /** True only while *this* caller holds a lock, so cleanup can't over-release. */
  const held = ref(false)

  function lock() {
    if (!import.meta.client || held.value) return
    held.value = true
    depth += 1
    if (depth === 1) applyLock()
  }

  function unlock() {
    if (!import.meta.client || !held.value) return
    held.value = false
    depth = Math.max(0, depth - 1)
    if (depth === 0) releaseLock()
  }

  if (source !== undefined) {
    watch(
      () => toValue(source),
      (open) => (open ? lock() : unlock()),
      { immediate: true },
    )
  }

  onBeforeUnmount(unlock)

  return { lock, unlock }
}
