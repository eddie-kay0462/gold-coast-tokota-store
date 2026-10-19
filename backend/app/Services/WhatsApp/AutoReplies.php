<?php

namespace App\Services\WhatsApp;

use App\Exceptions\WhatsAppSendRefused;
use App\Models\SiteSetting;
use App\Models\WhatsAppMessage;
use App\Support\BusinessHours;
use Illuminate\Support\Facades\Log;

/**
 * The two automatic replies, both off until an Admin turns them on:
 *
 *   Greeting    §14's default greeting (`whatsapp_greeting`), on a contact's
 *               very first message, or the first after 30 days of silence.
 *   After hours a short note outside business hours (§14: Monday – Saturday,
 *               9:00 AM – 5:00 PM GMT), once per closed spell.
 *
 * Never both at once: the greeting already states the hours. Never into a
 * conversation a person is having: no after-hours note if the team replied
 * in the last 30 minutes, whatever the clock says. Never to a contact who
 * replied STOP (the outbox refuses automation for them).
 */
class AutoReplies
{
    private const RETURNING_AFTER_DAYS = 30;

    private const TEAM_ACTIVE_MINUTES = 30;

    public function __construct(private readonly WhatsAppOutbox $outbox) {}

    public function handle(WhatsAppMessage $inbound): ?WhatsAppMessage
    {
        $settings = SiteSetting::current();
        $conversation = $inbound->conversation()->with('contact')->firstOrFail();

        try {
            if ($settings->whatsapp_auto_greeting && filled($settings->whatsapp_greeting) && $this->isNewOrReturning($inbound)) {
                return $this->send($conversation, (string) $settings->whatsapp_greeting, 'greeting');
            }

            if ($settings->whatsapp_after_hours_enabled && $this->needsAfterHoursNote($inbound, BusinessHours::from($settings->whatsapp_hours))) {
                return $this->send($conversation, $this->afterHoursText($settings), 'after_hours');
            }
        } catch (WhatsAppSendRefused $e) {
            // Opted out, typically. Nothing automatic goes to them.
            Log::info('WhatsApp auto-reply skipped.', ['conversation_id' => $conversation->id, 'reason' => $e->reason]);
        }

        return null;
    }

    /** The after-hours text: the owner's own, or one built from §14's hours. */
    public function afterHoursText(SiteSetting $settings): string
    {
        if (filled($settings->whatsapp_after_hours_message)) {
            return (string) $settings->whatsapp_after_hours_message;
        }

        $hours = BusinessHours::from($settings->whatsapp_hours)->describe();

        return "Thank you for contacting Gold Coast Tokota. Our team typically responds during business hours: {$hours}. We'll reply to your message then.";
    }

    private function isNewOrReturning(WhatsAppMessage $inbound): bool
    {
        $previous = WhatsAppMessage::query()
            ->where('conversation_id', $inbound->conversation_id)
            ->where('id', '!=', $inbound->id)
            ->where('status', '!=', 'draft')
            ->latest('sent_at')
            ->value('sent_at');

        return $previous === null
            || $inbound->sent_at->diffInDays($previous, absolute: true) >= self::RETURNING_AFTER_DAYS;
    }

    private function needsAfterHoursNote(WhatsAppMessage $inbound, BusinessHours $hours): bool
    {
        if ($hours->isOpen(now())) {
            return false;
        }

        $teamActive = WhatsAppMessage::query()
            ->where('conversation_id', $inbound->conversation_id)
            ->where('direction', 'outbound')
            ->whereIn('source', ['agent', 'phone_app'])
            ->where('sent_at', '>=', now()->subMinutes(self::TEAM_ACTIVE_MINUTES))
            ->exists();

        if ($teamActive) {
            return false;
        }

        $closedSince = $hours->closedSince(now());

        return ! WhatsAppMessage::query()
            ->where('conversation_id', $inbound->conversation_id)
            ->where('source', 'automation')
            ->where('meta->automation', 'after_hours')
            ->when($closedSince, fn ($q) => $q->where('sent_at', '>=', $closedSince))
            ->exists();
    }

    private function send($conversation, string $body, string $kind): WhatsAppMessage
    {
        $message = $this->outbox->text($conversation, $body, null, 'automation');
        $message->update(['meta' => [...($message->meta ?? []), 'automation' => $kind]]);

        return $message;
    }
}
