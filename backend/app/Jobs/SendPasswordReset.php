<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Notifications\NotificationMessage;
use App\Notifications\NotificationRecipient;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends the reset link through the same dispatcher every other transactional
 * message uses (Feature 8), rather than through Laravel's built-in
 * ResetPassword notification.
 *
 * The built-in one builds its URL from `route('password.reset')`, a named web
 * route this API has no reason to define — it is headless, and the page that
 * accepts the token belongs to the storefront. Overriding
 * `Customer::sendPasswordResetNotification` to come here keeps every customer
 * email in one place with one voice.
 */
class SendPasswordReset implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly Customer $customer,
        public readonly NotificationMessage $message,
    ) {}

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $dispatcher->send(
            new NotificationRecipient(
                email: $this->customer->email,
                // Explicitly no phone: this message is email-only by design.
                phone: null,
                name: $this->customer->name,
            ),
            $this->message,
        );
    }
}
