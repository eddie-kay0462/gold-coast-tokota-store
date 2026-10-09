<?php

namespace App\Services\Returns;

use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * §9 and §21 of the brand document, as code.
 *
 * Every rule here is transcribed, not designed — §22.2 and §22.3 forbid
 * inventing or altering a policy, so where the text is silent this class
 * refuses rather than guesses.
 *
 * The one place it interprets: §9 excludes "clearance or sale items, unless
 * defective", and separately lists the three accepted return reasons — the
 * product is defective, the wrong item was delivered, or it arrived damaged.
 * All three are the seller's error, so the exception is read as covering all
 * three rather than the single word "defective". A size exchange on a
 * clearance item is not covered, which is the case the wording exists for.
 */
class ReturnPolicy
{
    /** §9: "Returns are accepted within 7 days of receiving the order." */
    public const WINDOW_DAYS = 7;

    /** §9's accepted return reasons — all three are Gold Coast Tokota's error. */
    public const FAULT_REASONS = ['defective', 'wrong_item', 'damaged_in_transit'];

    /** §9's exchange path: "Exchanges are available for incorrect size…" */
    public const EXCHANGE_REASONS = ['size_exchange'];

    public const REASONS = [...self::FAULT_REASONS, ...self::EXCHANGE_REASONS];

    /** §9: "Approved refunds are processed within 7–14 business days." */
    public const REFUND_BUSINESS_DAYS = [7, 14];

    /**
     * Assess a request against the policy.
     *
     * @return array{is_eligible: bool, ineligible_reason: string|null, window_closes_at: Carbon|null, refund_amount: int|null}
     */
    public function assess(Order $order, string $reason): array
    {
        $windowClosesAt = $this->windowClosesAt($order);
        $ineligibleReason = $this->ineligibleReason($order, $reason, $windowClosesAt);

        return [
            'is_eligible' => $ineligibleReason === null,
            'ineligible_reason' => $ineligibleReason,
            'window_closes_at' => $windowClosesAt,
            'refund_amount' => $ineligibleReason === null ? $this->refundAmount($order, $reason) : null,
        ];
    }

    /** Null until the order is marked delivered — the clock starts on receipt. */
    public function windowClosesAt(Order $order): ?Carbon
    {
        return $order->delivered_at?->copy()->addDays(self::WINDOW_DAYS);
    }

    private function ineligibleReason(Order $order, string $reason, ?Carbon $windowClosesAt): ?string
    {
        if ($order->status === 'refunded') {
            return 'This order has already been refunded.';
        }

        if ($order->delivered_at === null) {
            // Not a refusal on the merits: the seven days run from receipt, so
            // until the order is marked delivered there is no window to be
            // inside or outside of.
            return 'This order has not been marked as delivered, so the 7-day return window has not started.';
        }

        if ($windowClosesAt !== null && $windowClosesAt->isPast()) {
            return 'The 7-day return window closed on '.$windowClosesAt->toFormattedDateString().'.';
        }

        $custom = $this->nonReturnableItemNames($order);

        if ($custom !== []) {
            return 'Custom-made and personalised products are non-returnable ('
                .implode(', ', $custom).').';
        }

        // §9: clearance and sale items come back only when something is wrong
        // with them — a change of size is not.
        if (in_array($reason, self::EXCHANGE_REASONS, true) && $this->containsSaleItem($order)) {
            return 'Sale and clearance items can only be returned if they are defective.';
        }

        return null;
    }

    /**
     * §9: "Shipping charges are non-refundable unless the error was caused by
     * Gold Coast Tokota." All three fault reasons are its error, so the refund
     * is the whole order. An exchange refunds nothing — the pair is swapped.
     */
    private function refundAmount(Order $order, string $reason): ?int
    {
        return in_array($reason, self::FAULT_REASONS, true) ? $order->total : null;
    }

    /** @return list<string> */
    private function nonReturnableItemNames(Order $order): array
    {
        return $order->items
            ->filter(fn ($item) => $item->product && ! $item->product->is_returnable)
            ->map(fn ($item) => $item->product_name)
            ->unique()
            ->values()
            ->all();
    }

    private function containsSaleItem(Order $order): bool
    {
        return $order->items->contains(
            fn ($item) => $item->product
                && $item->product->compare_at_ghs !== null
                && $item->product->compare_at_ghs > $item->product->base_price_ghs
        );
    }
}
