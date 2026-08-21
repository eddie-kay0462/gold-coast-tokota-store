/**
 * Reference-counted body scroll lock.
 *
 * Ported from `frontend/composables/useBodyScrollLock.ts` — same approach, same
 * reason: `body { overflow: hidden }` does not hold on iOS Safari, so the
 * position-fixed technique is required. Reference counting matters because a
 * modal can open on top of a drawer, and the inner one closing must not
 * release the outer one's lock.
 */
let locks = 0
let savedScrollY = 0

function lock() {
  if (typeof document === 'undefined') return
  if (locks === 0) {
    savedScrollY = window.scrollY
    const body = document.body
    body.style.position = 'fixed'
    body.style.top = `-${savedScrollY}px`
    body.style.left = '0'
    body.style.right = '0'
    body.style.width = '100%'
  }
  locks++
}

function unlock() {
  if (typeof document === 'undefined') return
  locks = Math.max(0, locks - 1)
  if (locks > 0) return
  const body = document.body
  body.style.position = ''
  body.style.top = ''
  body.style.left = ''
  body.style.right = ''
  body.style.width = ''
  window.scrollTo(0, savedScrollY)
}

export function useBodyScrollLock(active: Ref<boolean>) {
  let held = false
  const sync = (on: boolean) => {
    if (on && !held) { lock(); held = true }
    else if (!on && held) { unlock(); held = false }
  }
  watch(active, sync, { immediate: true })
  onBeforeUnmount(() => sync(false))
}
