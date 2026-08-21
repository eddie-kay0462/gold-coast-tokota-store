/**
 * Toast queue.
 *
 * The storefront's `Toast.vue` has sat unused because nothing decided where
 * toasts appear (open decision #10 in FOR_THE_TEAM.md). Deciding it here for
 * the admin: **bottom-right, stacked upward, newest nearest the corner.**
 *
 * Why bottom-right rather than the more common top-centre: this app's primary
 * actions live top-right (Save, Publish, Add product), and a top-centre toast
 * lands on the breadcrumb and search. Bottom-right keeps the confirmation near
 * where the eye already is after clicking a footer Save, and clear of the
 * sidebar. Admin has no fixed WhatsApp button to collide with, unlike the
 * storefront — which is why this decision does not automatically transfer.
 */
export type ToastTone = 'success' | 'error' | 'info'

export interface Toast {
  id: number
  tone: ToastTone
  title: string
  description?: string
  /** ms; errors persist until dismissed because they usually need reading. */
  timeout: number
}

let seq = 0

export function useToast() {
  const toasts = useState<Toast[]>('toasts', () => [])

  function dismiss(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  function push(tone: ToastTone, title: string, description?: string, timeout?: number) {
    const id = ++seq
    const ms = timeout ?? (tone === 'error' ? 0 : 4000)
    toasts.value = [...toasts.value, { id, tone, title, description, timeout: ms }]
    if (ms > 0 && typeof window !== 'undefined') setTimeout(() => dismiss(id), ms)
    return id
  }

  return {
    toasts,
    dismiss,
    success: (title: string, description?: string) => push('success', title, description),
    error: (title: string, description?: string) => push('error', title, description),
    info: (title: string, description?: string) => push('info', title, description),
  }
}
