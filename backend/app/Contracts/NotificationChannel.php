<?php

namespace App\Contracts;

use App\Notifications\NotificationMessage;
use App\Notifications\NotificationRecipient;

/**
 * One delivery channel for a transactional message (README Feature 8).
 *
 * The same shape as PaymentGateway and DeliveryProvider, and for the same
 * reason: the concrete implementations need credentials nobody has yet, so the
 * interface is what lets the rest of Feature 8 be built and tested now. Email
 * goes out through MailChannel, SMS through FishAfricaSmsService, and a
 * deployment with no Fish Africa secret falls back to LogSmsChannel rather
 * than pretending to have sent anything.
 */
interface NotificationChannel
{
    /** Channel identifier for logs and the admin settings panel: 'mail', 'sms'. */
    public function name(): string;

    /**
     * Deliver one message. Returns false when the recipient has no address for
     * this channel — a customer with no phone number is an ordinary case, not
     * a failure.
     *
     * Implementations throw on transport failure. The dispatcher catches and
     * logs; Feature 8's acceptance criteria make notification failure
     * non-fatal to the order or booking that triggered it.
     */
    public function send(NotificationRecipient $to, NotificationMessage $message): bool;
}
