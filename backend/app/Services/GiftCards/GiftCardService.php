<?php

namespace App\Services\GiftCards;

use App\Exceptions\GiftCardUnusableException;
use App\Models\AdminUser;
use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Every change to a gift card's balance goes through here, under a row lock,
 * with a ledger row beside it.
 *
 * A balance is taken when checkout creates the order, the same moment stock is
 * held, so two checkouts can't spend the same cedi twice. If that order never
 * gets paid (the hold lapses, it's cancelled, or its payment session never
 * opens) the balance goes back. If a payment lands after that, the balance is
 * taken again; if the card no longer covers it, the order is still paid (the
 * money arrived) and someone is told.
 *
 * Amounts are GHS minor units throughout. A USD order converts at its locked
 * rate in CheckoutSessionService; the card itself only ever holds cedis.
 */
class GiftCardService
{
    /** No 0/O or 1/I, so a code read aloud on WhatsApp can't be misheard. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * What the client has set in admin. `enabled` is whether gift cards are on
     * sale; spending one at checkout works whenever a card exists, so store
     * credit issued before launch can still be used.
     *
     * @return array{enabled: bool, amounts: list<int>, expiry_months: int|null, terms: string|null}
     */
    public function settings(): array
    {
        $settings = SiteSetting::current();

        return [
            'enabled' => (bool) $settings->gift_cards_enabled,
            'amounts' => array_values(array_map('intval', $settings->gift_card_amounts ?? [])),
            'expiry_months' => $settings->gift_card_expiry_months ?: null,
            'terms' => $settings->gift_card_terms,
        ];
    }

    /** Whether checkout should offer a code field at all. */
    public function redemptionOpen(): bool
    {
        return $this->settings()['enabled'] || GiftCard::query()->where('status', 'active')->where('balance', '>', 0)->exists();
    }

    /**
     * Creates a card with its opening balance. Expiry follows the setting at
     * the moment of issue; changing the setting later doesn't move cards
     * already out there.
     *
     * @param  array{recipient_name?: string|null, recipient_email?: string|null, sender_name?: string|null, message?: string|null, note?: string|null}  $details
     */
    public function issue(int $amountGhs, string $source, array $details = [], ?AdminUser $by = null, ?Order $purchaseOrder = null): GiftCard
    {
        $months = $this->settings()['expiry_months'];

        return DB::transaction(function () use ($amountGhs, $source, $details, $by, $purchaseOrder, $months) {
            $card = GiftCard::create([
                'code' => $this->newCode(),
                'source' => $source,
                'status' => 'active',
                'initial_balance' => $amountGhs,
                'balance' => $amountGhs,
                'recipient_name' => $details['recipient_name'] ?? null,
                'recipient_email' => $details['recipient_email'] ?? null,
                'sender_name' => $details['sender_name'] ?? null,
                'message' => $details['message'] ?? null,
                'note' => $details['note'] ?? null,
                'purchase_order_id' => $purchaseOrder?->id,
                'issued_by_admin_id' => $by?->id,
                'expires_at' => $months ? now()->addMonths($months) : null,
            ]);

            $this->record($card, 'issue', $amountGhs, order: $purchaseOrder, admin: $by);

            return $card;
        });
    }

    /** The card behind a typed code, if it can be spent now. */
    public function usable(string $code): GiftCard
    {
        $card = GiftCard::query()->withCode($code)->first();

        $this->assertUsable($card);

        return $card;
    }

    /**
     * Takes up to `$maxGhs` off the card for this order. Call inside the
     * transaction that creates the order. Returns what was taken.
     *
     * @throws GiftCardUnusableException
     */
    public function redeem(string $code, Order $order, int $maxGhs): int
    {
        $card = GiftCard::query()->withCode($code)->lockForUpdate()->first();
        $this->assertUsable($card);

        $applied = min($card->balance, $maxGhs);
        if ($applied <= 0) {
            return 0;
        }

        $card->decrement('balance', $applied);
        $this->record($card, 'redeem', -$applied, order: $order);

        $order->forceFill(['gift_card_id' => $card->id, 'gift_card_amount_ghs' => $applied]);

        return $applied;
    }

    /**
     * Gives an unpaid order's balance back to its card. Safe to call more
     * than once, and a no-op on an order that took nothing or was paid.
     */
    public function release(Order $order, string $reason): void
    {
        if (! $order->gift_card_id || $order->gift_card_amount_ghs <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $reason) {
            $locked = Order::query()->lockForUpdate()->find($order->id);
            if (! $locked || $locked->gift_card_released_at || ! in_array($locked->status, ['pending', 'cancelled'], true)) {
                return;
            }

            $card = GiftCard::query()->lockForUpdate()->find($locked->gift_card_id);
            if ($card) {
                $card->increment('balance', $locked->gift_card_amount_ghs);
                $this->record($card, 'release', $locked->gift_card_amount_ghs, order: $locked, note: $reason);
            }

            $locked->update(['gift_card_released_at' => now()]);
            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * A paid order is refunded: what the card paid goes back on the card,
     * once. Returns the GHS amount returned (0 if there was nothing to).
     */
    public function refundToCard(Order $order, AdminUser $by): int
    {
        if (! $order->gift_card_id || $order->gift_card_amount_ghs <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($order, $by) {
            $locked = Order::query()->lockForUpdate()->find($order->id);
            if (! $locked || $locked->gift_card_refunded_at) {
                return 0;
            }

            $card = GiftCard::query()->lockForUpdate()->find($locked->gift_card_id);
            if ($card) {
                $card->increment('balance', $locked->gift_card_amount_ghs);
                $this->record($card, 'refund', $locked->gift_card_amount_ghs, order: $locked, admin: $by, note: "Order {$locked->reference} refunded");
            }

            $locked->update(['gift_card_refunded_at' => now()]);
            $order->setRawAttributes($locked->getAttributes(), true);

            return $card ? $locked->gift_card_amount_ghs : 0;
        });
    }

    /** Releases every unpaid order whose stock hold has lapsed. */
    public function releaseLapsed(): int
    {
        $released = 0;

        Order::query()
            ->where('status', 'pending')
            ->where('gift_card_amount_ghs', '>', 0)
            ->whereNull('gift_card_released_at')
            ->where('reservation_expires_at', '<=', now())
            ->each(function (Order $order) use (&$released) {
                $this->release($order, 'Order not paid before its hold lapsed');
                $released++;
            });

        return $released;
    }

    /**
     * A payment arrived for an order whose balance had already gone back to
     * the card. Takes it again, inside the payment's transaction. Returns how
     * much the card could no longer cover (0 when it all came back).
     */
    public function reclaim(Order $order): int
    {
        if (! $order->gift_card_released_at || ! $order->gift_card_id) {
            return 0;
        }

        $card = GiftCard::query()->lockForUpdate()->find($order->gift_card_id);
        $wanted = $order->gift_card_amount_ghs;
        $taken = $card ? min($card->balance, $wanted) : 0;

        if ($taken > 0) {
            $card->decrement('balance', $taken);
            $this->record($card, 'reclaim', -$taken, order: $order, note: 'Payment arrived after the hold lapsed');
        }

        $order->update(['gift_card_released_at' => null]);

        $shortfall = $wanted - $taken;
        if ($shortfall > 0) {
            Log::error('A late payment\'s gift card no longer covers its share of the order.', [
                'order_reference' => $order->reference,
                'gift_card' => $card?->lastFour(),
                'shortfall_ghs_minor' => $shortfall,
            ]);
        }

        return $shortfall;
    }

    /** An Admin's correction: sets the balance, recording the difference. */
    public function adjust(GiftCard $card, int $newBalance, AdminUser $by, string $note): GiftCard
    {
        return DB::transaction(function () use ($card, $newBalance, $by, $note) {
            $locked = GiftCard::query()->lockForUpdate()->findOrFail($card->id);
            $delta = $newBalance - $locked->balance;

            if ($delta !== 0) {
                $locked->update(['balance' => $newBalance]);
                $this->record($locked, 'adjust', $delta, admin: $by, note: $note);
            }

            return $locked;
        });
    }

    /** @throws GiftCardUnusableException */
    private function assertUsable(?GiftCard $card): void
    {
        if (! $card || $card->status !== 'active') {
            throw new GiftCardUnusableException('invalid');
        }
        if ($card->isExpired()) {
            throw new GiftCardUnusableException('expired');
        }
        if ($card->balance <= 0) {
            throw new GiftCardUnusableException('empty');
        }
    }

    private function record(GiftCard $card, string $type, int $amount, ?Order $order = null, ?AdminUser $admin = null, ?string $note = null): void
    {
        GiftCardTransaction::create([
            'gift_card_id' => $card->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $card->fresh()->balance,
            'order_id' => $order?->id,
            'admin_user_id' => $admin?->id,
            'note' => $note,
        ]);
    }

    private function newCode(): string
    {
        do {
            $groups = [];
            for ($g = 0; $g < 3; $g++) {
                $chunk = '';
                for ($i = 0; $i < 4; $i++) {
                    $chunk .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
                }
                $groups[] = $chunk;
            }
            $code = 'GCT-'.implode('-', $groups);
        } while (GiftCard::query()->where('code', $code)->exists());

        return $code;
    }
}
