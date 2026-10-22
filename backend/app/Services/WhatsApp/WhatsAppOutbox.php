<?php

namespace App\Services\WhatsApp;

use App\Exceptions\WhatsAppSendRefused;
use App\Jobs\SendWhatsAppMessage;
use App\Models\AdminUser;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Every message the brand sends goes through here: typed in the inbox,
 * sent by automation, or a payment link. The rules WhatsApp enforces are
 * checked before anything is queued, so a refusal is an immediate, readable
 * 422 rather than a failed tick later:
 *
 *   - Free-form text and buttons only inside the 24-hour service window.
 *   - Outside it, only an approved template.
 *   - Nothing automated or templated to someone who replied STOP.
 *
 * A message is stored as `queued` and appears in the thread at once;
 * SendWhatsAppMessage sends it, and the status webhooks move it on from
 * there.
 */
class WhatsAppOutbox
{
    public function text(WhatsAppConversation $conversation, string $body, ?AdminUser $by, string $source = 'agent'): WhatsAppMessage
    {
        $this->requireWindow($conversation);

        if ($source === 'automation') {
            $this->requireNotOptedOut($conversation);
        }

        return $this->queue($conversation, [
            'kind' => 'text',
            'body' => $body,
            'source' => $source,
            'sent_by_admin_id' => $by?->id,
        ]);
    }

    /**
     * @param  list<string>  $parameters  values for the body's {{1}}, {{2}}, …
     */
    public function template(WhatsAppConversation $conversation, WhatsAppTemplate $template, array $parameters, ?AdminUser $by, string $source = 'agent'): WhatsAppMessage
    {
        if ($template->status !== 'approved') {
            throw WhatsAppSendRefused::templateNotApproved($template->name);
        }

        $this->requireNotOptedOut($conversation);

        $body = $template->bodyText();
        foreach (array_values($parameters) as $i => $value) {
            // A callback, not a replacement string: a customer's "$1" is text.
            $body = preg_replace_callback('/\{\{\s*'.($i + 1).'\s*\}\}/', fn () => (string) $value, $body) ?? $body;
        }

        return $this->queue($conversation, [
            'kind' => 'template',
            'body' => $body,
            'template_name' => $template->name,
            'source' => $source,
            'sent_by_admin_id' => $by?->id,
            'meta' => ['template' => [
                'name' => $template->name,
                'language' => $template->language,
                'components' => $parameters === [] ? [] : [[
                    'type' => 'body',
                    'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($parameters)),
                ]],
            ]],
        ]);
    }

    /** A message with one link button, e.g. a payment link. */
    public function linkButton(WhatsAppConversation $conversation, string $body, string $buttonText, string $url, ?AdminUser $by, string $source = 'agent'): WhatsAppMessage
    {
        $this->requireWindow($conversation);

        return $this->queue($conversation, [
            'kind' => 'interactive',
            'body' => $body,
            'source' => $source,
            'sent_by_admin_id' => $by?->id,
            'meta' => ['cta' => ['button' => $buttonText, 'url' => $url]],
        ]);
    }

    /**
     * An intern's reply, kept in the thread for a Staff member or an Admin to
     * send. A draft goes nowhere until then.
     */
    public function draft(WhatsAppConversation $conversation, string $body, AdminUser $by): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'kind' => 'text',
            'body' => $body,
            'status' => 'draft',
            'source' => 'agent',
            'sent_by_admin_id' => $by->id,
            'sent_at' => now(),
            'meta' => ['drafted_by' => ['id' => $by->id, 'name' => $by->name]],
        ]);
    }

    /** Sends a draft, optionally edited. The sender is recorded as the sender. */
    public function sendDraft(WhatsAppMessage $draft, AdminUser $by, ?string $body = null): WhatsAppMessage
    {
        if ($draft->status !== 'draft') {
            throw WhatsAppSendRefused::notADraft();
        }

        $conversation = $draft->conversation;
        $this->requireWindow($conversation);

        $draft->update([
            'body' => $body ?? $draft->body,
            'status' => 'queued',
            'sent_by_admin_id' => $by->id,
            'sent_at' => now(),
        ]);

        $this->afterQueued($conversation, $draft);

        return $draft;
    }

    private function requireWindow(WhatsAppConversation $conversation): void
    {
        if (! $conversation->serviceWindowOpen()) {
            throw WhatsAppSendRefused::windowClosed();
        }
    }

    private function requireNotOptedOut(WhatsAppConversation $conversation): void
    {
        if ($conversation->contact?->opted_out_at) {
            throw WhatsAppSendRefused::optedOut();
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function queue(WhatsAppConversation $conversation, array $attributes): WhatsAppMessage
    {
        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'status' => 'queued',
            'sent_at' => now(),
            ...$attributes,
        ]);

        $this->afterQueued($conversation, $message);

        return $message;
    }

    private function afterQueued(WhatsAppConversation $conversation, WhatsAppMessage $message): void
    {
        DB::transaction(function () use ($conversation, $message) {
            $locked = WhatsAppConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $locked->update([
                'last_message_at' => $message->sent_at,
                'last_message_preview' => $message->preview(),
                // An agent replying means the thread has been read.
                'unread_count' => $message->source === 'agent' ? 0 : $locked->unread_count,
                'status' => 'open',
            ]);
        });

        // After the commit, so the job never reads a message that is not
        // there yet.
        SendWhatsAppMessage::dispatch($message->id)->afterCommit();
    }
}
