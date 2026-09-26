<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationChannel;
use App\Notifications\NotificationMessage;
use App\Notifications\NotificationRecipient;
use Illuminate\Support\Facades\Log;

/**
 * The SMS channel used when no Fish Africa credentials are configured.
 *
 * The same call FakeGateway makes for payments: a system that looks like it is
 * texting customers and is not should be noisy about it rather than quiet. The
 * message is written to the log in full so the copy can be reviewed before a
 * single real text is ever paid for.
 */
class LogSmsChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'sms';
    }

    public function send(NotificationRecipient $to, NotificationMessage $message): bool
    {
        if (! $to->phone || ! $message->sms) {
            return false;
        }

        Log::info('SMS not sent — no Fish Africa credentials configured.', [
            'notification' => $message->key,
            'to' => FishAfricaSmsService::normalisePhone($to->phone) ?? $to->phone,
            'body' => $message->sms,
            'length' => mb_strlen($message->sms),
        ]);

        return true;
    }
}
