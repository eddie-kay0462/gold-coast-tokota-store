/**
 * The gift card (or store credit) code applied at checkout, shared between the
 * order summary, where it's typed, and the payment step, which sends it.
 *
 * Applying only checks the balance (`POST /gift-cards/balance`); nothing is
 * taken until the order is placed, when the API spends it inside the same
 * transaction that holds the stock. So the figure shown here is a preview, and
 * the order's own `gift_card_amount` is what was actually taken.
 */
export interface AppliedGiftCard {
  code: string
  lastFour: string
  /** What's on the card, GHS minor units. */
  balanceGhs: number
}

export type ApplyResult =
  | { ok: true }
  | { ok: false, message: string, notAGiftCard: boolean }

export function useGiftCardCode() {
  const applied = useState<AppliedGiftCard | null>('checkout-gift-card', () => null)
  const api = useApi()

  async function apply(code: string): Promise<ApplyResult> {
    try {
      const res = await api<{ data: { last_four: string, balance: number } }>('/gift-cards/balance', {
        method: 'POST',
        body: { code },
      })
      applied.value = { code: code.trim(), lastFour: res.data.last_four, balanceGhs: res.data.balance }
      return { ok: true }
    } catch (err: unknown) {
      const e = err as { statusCode?: number, data?: { message?: string } }
      if (e.statusCode === 422) {
        const message = e.data?.message ?? 'That code isn’t valid.'
        // Expired or empty is a real card; anything else may be a discount code.
        return { ok: false, message, notAGiftCard: !/expired|no balance/i.test(message) }
      }
      if (e.statusCode === 429) {
        return { ok: false, message: 'Too many tries. Wait a minute and try again.', notAGiftCard: false }
      }
      return { ok: false, message: 'We couldn’t check that code just now. Try again in a moment.', notAGiftCard: false }
    }
  }

  function clear() {
    applied.value = null
  }

  /** How much the card takes off a total (GHS minor units). */
  function takenOff(totalGhs: number): number {
    return applied.value ? Math.min(applied.value.balanceGhs, totalGhs) : 0
  }

  return { applied, apply, clear, takenOff }
}
