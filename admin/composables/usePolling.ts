/**
 * Runs `tick` every `intervalMs` while the page is visible, and once more as
 * soon as it becomes visible again.
 *
 * The README's freshness model is polling, not WebSockets, and it asks that
 * polling stop while the tab is in the background (Page Visibility API). A
 * hidden inbox polling every five seconds is load nobody is reading. Ticks
 * never overlap: a slow response delays the next one rather than stacking.
 */
export function usePolling(tick: () => Promise<unknown> | unknown, intervalMs: number) {
  let timer: ReturnType<typeof setTimeout> | null = null
  let running = false
  let stopped = true

  async function run() {
    if (stopped || running) return
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return schedule()
    running = true
    try {
      await tick()
    } catch {
      // A missed poll is not worth an error on screen; the next one retries.
    } finally {
      running = false
      schedule()
    }
  }

  function schedule() {
    if (stopped) return
    if (timer) clearTimeout(timer)
    timer = setTimeout(run, intervalMs)
  }

  function onVisibility() {
    if (document.visibilityState === 'visible') run()
  }

  function start() {
    if (!stopped) return
    stopped = false
    document.addEventListener('visibilitychange', onVisibility)
    schedule()
  }

  function stop() {
    stopped = true
    if (timer) clearTimeout(timer)
    timer = null
    if (typeof document !== 'undefined') document.removeEventListener('visibilitychange', onVisibility)
  }

  /** Runs a tick now (e.g. after sending) and restarts the interval from here. */
  function now() {
    return run()
  }

  onMounted(start)
  onBeforeUnmount(stop)

  return { start, stop, now }
}
