import type { Money } from './common'

export type GiftCardSource = 'purchase' | 'store_credit'
export type GiftCardStatus = 'active' | 'disabled'
export type GiftCardTransactionType = 'issue' | 'redeem' | 'release' | 'reclaim' | 'adjust'

/** One change to a card's balance. `amount` is signed: negative took from it. */
export interface GiftCardTransaction {
  id: number
  type: GiftCardTransactionType
  amount: Money
  balanceAfter: Money
  orderReference: string | null
  admin: string | null
  note: string | null
  createdAt: string
}

/** A gift card someone bought, or store credit an Admin issued. Balances are GHS. */
export interface GiftCard {
  id: number
  code: string
  source: GiftCardSource
  status: GiftCardStatus
  isExpired: boolean
  initialBalance: Money
  balance: Money
  recipientName: string | null
  recipientEmail: string | null
  senderName: string | null
  message: string | null
  note: string | null
  purchaseOrderReference?: string | null
  issuedBy?: string | null
  expiresAt: string | null
  createdAt: string
  transactions?: GiftCardTransaction[]
}

/** The client's policy. Amounts are GHS minor units. */
export interface GiftCardSettings {
  enabled: boolean
  amounts: number[]
  expiryMonths: number | null
  terms: string | null
}
