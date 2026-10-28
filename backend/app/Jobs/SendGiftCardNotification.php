<?php

namespace App\Jobs;

use App\Models\GiftCard;
use App\Notifications\NotificationRecipient;
use App\Notifications\TransactionalMessages;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Emails a gift card or store credit code to whoever it is for. */
class SendGiftCardNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly GiftCard $card) {}

    public function handle(NotificationDispatcher $dispatcher): void
    {
        if (! $this->card->recipient_email) {
            return;
        }

        $dispatcher->send(
            new NotificationRecipient(
                email: $this->card->recipient_email,
                phone: null,
                name: $this->card->recipient_name ?: 'there',
            ),
            TransactionalMessages::giftCard($this->card),
        );
    }
}
