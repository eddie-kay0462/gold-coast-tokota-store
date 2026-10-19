<?php

namespace App\Services\Notifications;

/**
 * Builds the channel list for this deployment.
 *
 * Email is always on — `MAIL_MAILER=log` is a valid configuration and the
 * right one in development. SMS resolves to Fish Africa when credentials
 * exist and to LogSmsChannel when they do not, the same shape
 * PaymentGatewayFactory uses to fall back to FakeGateway: the feature stays
 * exercised end to end either way, and the log says plainly which one ran.
 *
 * WhatsApp is always in the list: WhatsAppChannel sends only to contacts with
 * a WhatsApp conversation, and through whichever WhatsApp client is bound
 * (the logging stand-in without Cloud API credentials).
 */
class NotificationChannelFactory
{
    public function make(): NotificationDispatcher
    {
        return new NotificationDispatcher([
            new MailChannel,
            FishAfricaSmsService::isConfigured()
                ? new FishAfricaSmsService
                : new LogSmsChannel,
            // Only ever reaches people who have written to the brand on
            // WhatsApp; a no-op for everyone else. See WhatsAppChannel.
            app(WhatsAppChannel::class),
        ]);
    }
}
