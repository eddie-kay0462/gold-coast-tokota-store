<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationChannel;
use App\Exceptions\WhatsAppSendRefused;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppTemplate;
use App\Notifications\NotificationMessage;
use App\Notifications\NotificationRecipient;
use App\Services\WhatsApp\WhatsAppOutbox;
use App\Support\PhoneNumber;

/**
 * Order and booking updates on WhatsApp, alongside email and SMS. §8 and
 * §16 promise shipping and production updates "via email or WhatsApp", and
 * §22.17 asks for both.
 *
 * It joins the existing triggers (order placed, dispatched, booking
 * submitted, confirmed, waitlist promoted) and adds none of its own.
 *
 * **Only to people who have written to the brand on WhatsApp.** WhatsApp
 * requires opt-in for business-initiated messages, and a customer who
 * checked out on the website has given it for email and SMS, not for this.
 * A contact who replied STOP gets nothing.
 *
 *   inside the 24-hour window  the SMS line, as a plain text (free, and the
 *                              copy is already short)
 *   outside it                 the approved template named in
 *                              config('whatsapp.templates.<key>'), if any
 *   neither                    nothing; email and SMS still go
 */
class WhatsAppChannel implements NotificationChannel
{
    /** Which notifications go here, and the template values each sends outside the window. */
    private const KEYS = ['order_placed', 'order_shipped', 'booking_submitted', 'booking_confirmed', 'waitlist_promoted'];

    public function __construct(private readonly WhatsAppOutbox $outbox) {}

    public function name(): string
    {
        return 'whatsapp';
    }

    public function send(NotificationRecipient $to, NotificationMessage $message): bool
    {
        if (! in_array($message->key, self::KEYS, true) || ! $message->sms) {
            return false;
        }

        $waId = PhoneNumber::toWaId($to->phone);
        $contact = $waId ? WhatsAppContact::query()->with('conversation.contact')->where('wa_id', $waId)->first() : null;
        $conversation = $contact?->conversation;

        if (! $conversation || $contact->opted_out_at) {
            return false;
        }

        try {
            if ($conversation->serviceWindowOpen()) {
                $sent = $this->outbox->text($conversation, $this->plain($message->sms), null, 'automation');
            } elseif ($template = $this->template($message->key)) {
                $sent = $this->outbox->template($conversation, $template, $this->values($message, $to), null, 'automation');
            } else {
                return false;
            }
        } catch (WhatsAppSendRefused) {
            return false;
        }

        $sent->update(['meta' => [...($sent->meta ?? []), 'automation' => $message->key]]);

        return true;
    }

    /** The SMS copy opens "Gold Coast Tokota: "; on WhatsApp the sender already says so. */
    private function plain(string $sms): string
    {
        return preg_replace('/^Gold Coast Tokota:\s*/', '', $sms) ?? $sms;
    }

    private function template(string $key): ?WhatsAppTemplate
    {
        $name = config("whatsapp.templates.{$key}");

        return $name
            ? WhatsAppTemplate::query()->where('name', $name)->where('status', 'approved')->first()
            : null;
    }

    /**
     * The body values each template is written with, in order. Documented in
     * config/whatsapp.php so the business writes the templates to match.
     *
     * @return list<string>
     */
    private function values(NotificationMessage $message, NotificationRecipient $to): array
    {
        $order = $message->data['order'] ?? null;

        return match ($message->key) {
            'order_placed' => [$to->firstName(), (string) $order?->reference, (string) ($message->data['total'] ?? '')],
            'order_shipped' => [$to->firstName(), (string) $order?->reference],
            default => [$to->firstName()],
        };
    }
}
