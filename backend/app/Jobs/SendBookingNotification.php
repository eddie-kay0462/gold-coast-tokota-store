<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Notifications\NotificationRecipient;
use App\Notifications\TransactionalMessages;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The three booking triggers README Feature 8 names: submitted, confirmed, and
 * promoted off the waitlist.
 *
 * Queued for the same reason as the order job — a customer pressing "Book"
 * should not wait for an SMS gateway, and Feature 8 requires that a failure
 * here never blocks the booking itself.
 */
class SendBookingNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly Booking $booking,
        public readonly string $reason,
    ) {}

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $booking = $this->booking->load(['workshopSession.workshopType', 'customer']);

        $message = match ($this->reason) {
            'confirmed' => TransactionalMessages::bookingConfirmed($booking),
            'waitlist_promoted' => TransactionalMessages::waitlistPromoted($booking),
            default => TransactionalMessages::bookingSubmitted($booking),
        };

        $dispatcher->send(NotificationRecipient::forBooking($booking), $message);
    }
}
