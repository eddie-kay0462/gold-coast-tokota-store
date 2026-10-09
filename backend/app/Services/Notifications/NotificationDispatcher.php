<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationChannel;
use App\Notifications\NotificationMessage;
use App\Notifications\NotificationRecipient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fans one message out across every configured channel.
 *
 * **The important behaviour here is that it never throws.** Feature 8's
 * acceptance criteria are explicit: "SMS/email failures are logged and do not
 * block order/booking completion (notification failure is non-fatal to the
 * underlying transaction)". A customer whose money has been taken and whose
 * stock has been decremented must not have their order fail because a text
 * message bounced — so each channel is attempted independently and a failure
 * in one is logged and stepped over, leaving the others to deliver.
 *
 * That also means a caller cannot tell whether delivery succeeded, which is
 * deliberate: retrying is the queue's job (see the jobs in App\Jobs, which set
 * their own `$tries`), not the caller's.
 */
class NotificationDispatcher
{
    /** @param  list<NotificationChannel>  $channels */
    public function __construct(private readonly array $channels) {}

    /** @return array<string, bool> Channel name => whether it delivered. */
    public function send(NotificationRecipient $to, NotificationMessage $message): array
    {
        $results = [];

        foreach ($this->channels as $channel) {
            try {
                $results[$channel->name()] = $channel->send($to, $message);
            } catch (Throwable $e) {
                $results[$channel->name()] = false;

                Log::error('Notification channel failed.', [
                    'channel' => $channel->name(),
                    'notification' => $message->key,
                    'recipient' => $to->email ?? $to->phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
