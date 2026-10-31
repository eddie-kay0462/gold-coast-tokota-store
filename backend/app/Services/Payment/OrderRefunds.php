<?php

namespace App\Services\Payment;

use App\Models\AdminUser;
use App\Models\Order;
use App\Services\GiftCards\GiftCardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Refunding a paid order from the admin.
 *
 * The money goes back through Paystack to the card or mobile money account
 * that paid (§9: "Refunds use the original payment method"); any part a gift
 * card paid goes back on that card. A refund can be for less than the whole
 * charge, and several can be made, but never more in total than was paid.
 * §9 also keeps delivery charges unless the mistake was Gold Coast Tokota's,
 * so the admin screen suggests the paid amount less delivery; whoever sends
 * it decides.
 *
 * The order is marked `refunded` once all of it has gone back. Stock isn't
 * touched: a returned pair comes back through Returns and Inventory.
 */
final class OrderRefunds
{
    public const REFUNDABLE = ['paid', 'processing', 'shipped', 'delivered', 'inventory_conflict'];

    public function __construct(
        private readonly PaystackService $paystack,
        private readonly GiftCardService $giftCards,
    ) {}

    /** What Paystack took, in the order's currency: the total less any gift card. */
    public static function charged(Order $order): int
    {
        return max(0, $order->total - $order->gift_card_amount);
    }

    public static function refundable(Order $order): int
    {
        return max(0, self::charged($order) - $order->refunded_amount);
    }

    /**
     * @param  int  $amount  Through Paystack, order currency minor units. May be 0 when only the gift card part is going back.
     */
    public function refund(Order $order, int $amount, bool $returnGiftCard, AdminUser $by, ?string $note = null): Order
    {
        $this->guard($order, $amount, $returnGiftCard);

        if ($amount > 0) {
            if ($order->payment_gateway === 'paystack') {
                try {
                    $this->paystack->refund((string) $order->payment_reference, $amount, $note ?: "Refunded in admin by {$by->name}");
                } catch (RuntimeException $e) {
                    throw ValidationException::withMessages(['amount' => "Paystack didn’t take the refund: {$e->getMessage()}"]);
                }
            } elseif (app()->isProduction()) {
                // A test-gateway order in production would be a bug, not a refund.
                throw ValidationException::withMessages(['amount' => 'This order wasn’t paid through Paystack, so there’s nothing to send back there.']);
            }
        }

        DB::transaction(function () use ($order, $amount) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->update([
                'refunded_amount' => $locked->refunded_amount + $amount,
                'refunded_at' => now(),
            ]);
            $order->setRawAttributes($locked->getAttributes(), true);
        });

        if ($returnGiftCard) {
            $this->giftCards->refundToCard($order, $by);
        }

        $order->refresh();
        $cardDone = $order->gift_card_amount_ghs <= 0 || $order->gift_card_refunded_at !== null;
        if (self::refundable($order) === 0 && $cardDone) {
            $order->update(['status' => 'refunded']);
        }

        return $order;
    }

    private function guard(Order $order, int $amount, bool $returnGiftCard): void
    {
        if ($order->kind === 'gift_card') {
            throw ValidationException::withMessages([
                'amount' => 'A gift card purchase isn’t refunded here. Disable the card in Gift cards first, then refund it in the Paystack dashboard.',
            ]);
        }

        if (! in_array($order->status, self::REFUNDABLE, true)) {
            throw ValidationException::withMessages(['amount' => 'Only a paid order can be refunded.']);
        }

        if ($amount < 0 || $amount > self::refundable($order)) {
            throw ValidationException::withMessages(['amount' => 'That’s more than is left to refund on this order.']);
        }

        if ($amount === 0 && ! ($returnGiftCard && $order->gift_card_amount_ghs > 0 && $order->gift_card_refunded_at === null)) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount to refund.']);
        }
    }
}
