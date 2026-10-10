<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationChannel;
use App\Mail\TransactionalMail;
use App\Notifications\NotificationMessage;
use App\Notifications\NotificationRecipient;
use Illuminate\Support\Facades\Mail;

/**
 * Transactional email — README Feature 8's `OrderConfirmationMailer`, widened
 * to every trigger since all five send the same way and differ only in the
 * message handed to them.
 *
 * Needs no credential to be useful in development: `MAIL_MAILER=log` writes
 * the rendered email to the log, which is enough to build and review the whole
 * feature before a provider is chosen.
 */
class MailChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'mail';
    }

    public function send(NotificationRecipient $to, NotificationMessage $message): bool
    {
        if (! $to->email) {
            return false;
        }

        Mail::to($to->email, $to->name)->send(new TransactionalMail($message, $to));

        return true;
    }
}
