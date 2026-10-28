<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Jobs\SendGiftCardNotification;
use App\Models\GiftCard;
use App\Models\OrderItem;
use App\Services\GiftCards\GiftCardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * A paid gift card order becomes a gift card: issued with the amount bought,
 * in cedis whatever it was paid in, and emailed to the recipient.
 *
 * The issued card's id is written back onto the order line, under a lock, so
 * a retried job finds it there and issues nothing twice.
 */
class IssueGiftCardForPaidOrder implements ShouldQueue
{
    public function __construct(private readonly GiftCardService $giftCards) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        if (! $order->isGiftCardPurchase()) {
            return;
        }

        foreach ($order->items()->pluck('id') as $itemId) {
            $card = DB::transaction(function () use ($itemId, $order) {
                $item = OrderItem::query()->lockForUpdate()->find($itemId);
                $line = $item?->gift_card;

                if (! $line || isset($line['gift_card_id'])) {
                    return null;
                }

                $card = $this->giftCards->issue(
                    (int) $line['amount_ghs'],
                    GiftCard::SOURCE_PURCHASE,
                    [
                        'recipient_name' => $line['recipient_name'] ?? null,
                        'recipient_email' => $line['recipient_email'] ?? null,
                        'sender_name' => $line['sender_name'] ?? null,
                        'message' => $line['message'] ?? null,
                    ],
                    purchaseOrder: $order,
                );

                $item->update(['gift_card' => [...$line, 'gift_card_id' => $card->id]]);

                return $card;
            });

            if ($card) {
                SendGiftCardNotification::dispatch($card);
            }
        }
    }
}
