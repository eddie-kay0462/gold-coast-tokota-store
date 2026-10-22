<?php

namespace App\Services\WhatsApp;

use App\Events\WhatsAppMessageReceived;
use App\Jobs\DownloadWhatsAppMedia;
use App\Models\Customer;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns one verified Cloud API webhook delivery into inbox rows.
 *
 * A delivery is `entry[].changes[]`, each change naming its `field`:
 *
 *   messages                        customer messages, and delivery statuses
 *                                   for what we sent
 *   smb_message_echoes              coexistence: what the team typed in the
 *                                   WhatsApp Business app on the brand phone
 *   history                         coexistence: past chats, imported once at
 *                                   onboarding, in chunks
 *   smb_app_state_sync              coexistence: the phone's contact names
 *   message_template_status_update  Meta approved or rejected a template
 *
 * Meta delivers at least once and in no particular order, so every write is
 * idempotent: a message is keyed by its `wamid` (unique), and a status only
 * ever moves forward.
 *
 * The coexistence payload shapes come from Meta's partner documentation and
 * must be confirmed against real deliveries on the test number before the
 * brand number is connected (FOR_THE_TEAM.md, decision 49).
 */
class WhatsAppWebhookProcessor
{
    /** @param  array<string, mixed>  $payload */
    public function process(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                if (! $this->isOurNumber($value)) {
                    continue;
                }

                match ($change['field'] ?? null) {
                    'messages' => $this->messagesChange($value),
                    'smb_message_echoes' => $this->echoes($value),
                    'history' => $this->history($value),
                    'smb_app_state_sync' => $this->stateSync($value),
                    'message_template_status_update' => $this->templateStatus($value),
                    default => null,
                };
            }
        }
    }

    /**
     * A WABA can hold several numbers. Only the configured one is the
     * brand's; anything addressed to another number is not for this inbox.
     *
     * @param  array<string, mixed>  $value
     */
    private function isOurNumber(array $value): bool
    {
        $ours = config('whatsapp.phone_number_id');
        $theirs = $value['metadata']['phone_number_id'] ?? null;

        return blank($ours) || $theirs === null || (string) $theirs === (string) $ours;
    }

    /** @param  array<string, mixed>  $value */
    private function messagesChange(array $value): void
    {
        $names = collect($value['contacts'] ?? [])
            ->mapWithKeys(fn (array $c) => [(string) ($c['wa_id'] ?? '') => $c['profile']['name'] ?? null]);

        foreach ($value['messages'] ?? [] as $message) {
            $from = (string) ($message['from'] ?? '');

            if ($from !== '') {
                $this->inbound($message, $from, $names[$from] ?? null);
            }
        }

        foreach ($value['statuses'] ?? [] as $status) {
            $this->status($status);
        }
    }

    /** @param  array<string, mixed>  $message */
    private function inbound(array $message, string $from, ?string $profileName): void
    {
        $sentAt = $this->timestamp($message['timestamp'] ?? null);
        $contact = $this->contact($from, $profileName);
        $parsed = $this->parse($message);

        $stored = $this->store($contact, $message['id'] ?? null, [
            'direction' => 'inbound',
            'status' => 'received',
            'source' => 'customer',
            'sent_at' => $sentAt,
            ...$parsed['attributes'],
        ]);

        if (! $stored) {
            return; // a replay
        }

        $this->optOutKeywords($contact, (string) $stored->body);

        DB::transaction(function () use ($stored, $sentAt) {
            $conversation = WhatsAppConversation::query()->lockForUpdate()->findOrFail($stored->conversation_id);

            $conversation->fill([
                'status' => 'open',
                'last_inbound_at' => $this->latest($conversation->last_inbound_at, $sentAt),
                'unread_count' => $conversation->unread_count + 1,
            ]);
            $this->touchLastMessage($conversation, $stored);
            $conversation->save();
        });

        if ($parsed['media_id']) {
            DownloadWhatsAppMedia::dispatch($stored->id, $parsed['media_id']);
        }

        WhatsAppMessageReceived::dispatch($stored);
    }

    /** @param  array<string, mixed>  $status */
    private function status(array $status): void
    {
        $wamid = $status['id'] ?? null;
        $new = $status['status'] ?? null;

        if (! $wamid || ! $new) {
            return;
        }

        DB::transaction(function () use ($wamid, $new, $status) {
            $message = WhatsAppMessage::query()->where('wamid', $wamid)->lockForUpdate()->first();

            if (! $message) {
                return; // sent before this inbox existed, or from another system
            }

            if ($new === 'failed') {
                $error = $status['errors'][0] ?? [];
                $message->update([
                    'status' => 'failed',
                    'error' => mb_strimwidth((string) ($error['error_data']['details'] ?? $error['message'] ?? $error['title'] ?? 'WhatsApp could not deliver this message'), 0, 250, '…'),
                ]);

                return;
            }

            $rank = WhatsAppMessage::STATUS_RANK;

            if (isset($rank[$new]) && ($rank[$new] > ($rank[$message->status] ?? -1))) {
                $message->update(['status' => $new]);
            }
        });
    }

    /**
     * Coexistence: a reply typed on the brand's phone. Stored as outbound
     * from `phone_app`, so the admin thread shows the whole conversation, and
     * it counts as the team having answered (unread goes to zero).
     *
     * @param  array<string, mixed>  $value
     */
    private function echoes(array $value): void
    {
        foreach ($value['message_echoes'] ?? [] as $echo) {
            $to = (string) ($echo['to'] ?? '');

            if ($to === '') {
                continue;
            }

            $contact = $this->contact($to, null);
            $parsed = $this->parse($echo);

            $stored = $this->store($contact, $echo['id'] ?? null, [
                'direction' => 'outbound',
                'status' => 'sent',
                'source' => 'phone_app',
                'sent_at' => $this->timestamp($echo['timestamp'] ?? null),
                ...$parsed['attributes'],
            ]);

            if (! $stored) {
                continue;
            }

            $this->afterOutbound($stored);

            if ($parsed['media_id']) {
                DownloadWhatsAppMedia::dispatch($stored->id, $parsed['media_id']);
            }
        }
    }

    /**
     * Coexistence: past chats from the phone, imported once. Nothing here is
     * new to anyone, so nothing is marked unread and no automation runs.
     *
     * @param  array<string, mixed>  $value
     */
    private function history(array $value): void
    {
        foreach ($value['history'] ?? [] as $chunk) {
            if (! empty($chunk['errors'])) {
                // The business declined history sharing on the phone. Not a
                // failure: the inbox simply starts from today.
                Log::info('WhatsApp history sync declined.', ['error' => $chunk['errors'][0]['title'] ?? null]);

                continue;
            }

            foreach ($chunk['threads'] ?? [] as $thread) {
                $customer = (string) ($thread['id'] ?? '');

                if ($customer === '') {
                    continue;
                }

                $contact = $this->contact($customer, null);

                foreach ($thread['messages'] ?? [] as $message) {
                    $inbound = (string) ($message['from'] ?? '') === $customer;
                    $parsed = $this->parse($message);
                    $sentAt = $this->timestamp($message['timestamp'] ?? null);

                    $stored = $this->store($contact, $message['id'] ?? null, [
                        'direction' => $inbound ? 'inbound' : 'outbound',
                        'status' => $inbound ? 'received' : $this->historyStatus($message),
                        'source' => 'history',
                        'sent_at' => $sentAt,
                        ...$parsed['attributes'],
                    ]);

                    if (! $stored) {
                        continue;
                    }

                    DB::transaction(function () use ($stored, $inbound, $sentAt) {
                        $conversation = WhatsAppConversation::query()->lockForUpdate()->findOrFail($stored->conversation_id);

                        if ($inbound) {
                            $conversation->last_inbound_at = $this->latest($conversation->last_inbound_at, $sentAt);
                        }

                        if (! $conversation->last_message_at || $sentAt->greaterThan($conversation->last_message_at)) {
                            $this->touchLastMessage($conversation, $stored);
                        }

                        $conversation->save();
                    });
                }
            }
        }
    }

    /**
     * Coexistence: the contact names saved on the brand's phone. A saved name
     * is better than a WhatsApp profile name, which is whatever the customer
     * chose to call themselves.
     *
     * @param  array<string, mixed>  $value
     */
    private function stateSync(array $value): void
    {
        foreach ($value['state_sync'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'contact') {
                continue;
            }

            $waId = PhoneNumber::toWaId($item['contact']['phone_number'] ?? null);
            $name = $item['contact']['full_name'] ?? $item['contact']['first_name'] ?? null;

            if (! $waId || ! $name || ($item['action'] ?? null) === 'remove') {
                continue;
            }

            $contact = $this->contact($waId, null);
            $contact->update(['profile_name' => $name]);
        }
    }

    /** @param  array<string, mixed>  $value */
    private function templateStatus(array $value): void
    {
        $name = $value['message_template_name'] ?? null;
        $language = $value['message_template_language'] ?? null;

        if (! $name || ! $language) {
            return;
        }

        $template = WhatsAppTemplate::query()->firstOrNew(['name' => $name, 'language' => $language]);
        $template->fill([
            'meta_id' => isset($value['message_template_id']) ? (string) $value['message_template_id'] : $template->meta_id,
            'category' => $template->category ?? 'utility',
            'status' => strtolower((string) ($value['event'] ?? 'pending')),
            'rejection_reason' => ($value['reason'] ?? null) && $value['reason'] !== 'NONE' ? $value['reason'] : null,
        ])->save();
    }

    /**
     * Finds or creates the contact and their conversation, matching a
     * Customer on phone number the first time.
     */
    private function contact(string $waId, ?string $profileName): WhatsAppContact
    {
        $waId = PhoneNumber::toWaId($waId) ?? $waId;

        try {
            $contact = WhatsAppContact::query()->firstOrCreate(
                ['wa_id' => $waId],
                ['phone_e164' => PhoneNumber::e164($waId), 'profile_name' => $profileName, 'customer_id' => $this->matchCustomer($waId)],
            );
        } catch (UniqueConstraintViolationException) {
            // Two deliveries for a brand-new contact raced; the other won.
            $contact = WhatsAppContact::query()->where('wa_id', $waId)->firstOrFail();
        }

        if ($profileName && ! $contact->profile_name) {
            $contact->update(['profile_name' => $profileName]);
        }

        if (! $contact->customer_id && ($customerId = $this->matchCustomer($waId))) {
            $contact->update(['customer_id' => $customerId]);
        }

        try {
            WhatsAppConversation::query()->firstOrCreate(['contact_id' => $contact->id]);
        } catch (UniqueConstraintViolationException) {
            // Created concurrently; nothing to do.
        }

        return $contact;
    }

    /**
     * The customer record with this number, if exactly one has it. Two
     * accounts sharing a number (a family, a shop) are left for a person to
     * link, rather than guessed at.
     */
    private function matchCustomer(string $waId): ?int
    {
        $tail = substr($waId, -9);

        $ids = Customer::query()
            ->whereNotNull('phone')
            ->where('phone', 'like', '%'.substr($tail, -4))
            ->get(['id', 'phone'])
            ->filter(fn (Customer $c) => PhoneNumber::same($c->phone, $waId))
            ->pluck('id');

        return $ids->count() === 1 ? $ids->first() : null;
    }

    /**
     * Inserts one message, or returns null if this wamid is already stored.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function store(WhatsAppContact $contact, ?string $wamid, array $attributes): ?WhatsAppMessage
    {
        if ($wamid && WhatsAppMessage::query()->where('wamid', $wamid)->exists()) {
            return null;
        }

        $conversationId = WhatsAppConversation::query()->where('contact_id', $contact->id)->value('id');

        try {
            return WhatsAppMessage::create([
                'conversation_id' => $conversationId,
                'wamid' => $wamid,
                'provider' => 'cloud_api',
                ...$attributes,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function afterOutbound(WhatsAppMessage $message): void
    {
        DB::transaction(function () use ($message) {
            $conversation = WhatsAppConversation::query()->lockForUpdate()->findOrFail($message->conversation_id);
            $this->touchLastMessage($conversation, $message);
            $conversation->unread_count = 0;
            $conversation->save();
        });
    }

    private function touchLastMessage(WhatsAppConversation $conversation, WhatsAppMessage $message): void
    {
        $conversation->last_message_at = $this->latest($conversation->last_message_at, $message->sent_at);

        if ($conversation->last_message_at->equalTo($message->sent_at)) {
            $conversation->last_message_preview = $message->preview();
        }
    }

    /**
     * Reads a Cloud API message object into column values. `media_id` is
     * returned separately: the file itself is fetched by a queued job.
     *
     * @param  array<string, mixed>  $message
     * @return array{attributes: array<string, mixed>, media_id: ?string}
     */
    private function parse(array $message): array
    {
        $type = (string) ($message['type'] ?? 'unsupported');
        $content = $message[$type] ?? [];
        $meta = array_filter(['context' => $message['context'] ?? null, 'referral' => $message['referral'] ?? null]);

        $attributes = match ($type) {
            'text' => ['kind' => 'text', 'body' => $content['body'] ?? ''],
            'image', 'video', 'sticker', 'audio', 'document' => [
                'kind' => $type,
                'body' => $content['caption'] ?? null,
                'media_mime' => $content['mime_type'] ?? null,
                'media_filename' => $content['filename'] ?? null,
            ],
            'location' => [
                'kind' => 'location',
                'body' => trim(implode(', ', array_filter([$content['name'] ?? null, $content['address'] ?? null])))
                    ?: sprintf('%s, %s', $content['latitude'] ?? '?', $content['longitude'] ?? '?'),
            ],
            'interactive' => [
                'kind' => 'interactive',
                'body' => $content['button_reply']['title'] ?? $content['list_reply']['title'] ?? $content['nfm_reply']['body'] ?? null,
            ],
            'button' => ['kind' => 'interactive', 'body' => $content['text'] ?? null],
            'reaction' => ['kind' => 'system', 'body' => trim('Reacted '.($content['emoji'] ?? ''))],
            'template' => ['kind' => 'template', 'template_name' => $content['name'] ?? null, 'body' => null],
            default => ['kind' => 'system', 'body' => 'A message type the inbox cannot show yet ('.$type.'). Open WhatsApp on the phone to see it.'],
        };

        if ($type === 'location') {
            $meta['location'] = $content;
        }

        return [
            'attributes' => [...$attributes, 'meta' => $meta ?: null],
            'media_id' => in_array($type, ['image', 'video', 'sticker', 'audio', 'document'], true) ? ($content['id'] ?? null) : null,
        ];
    }

    /**
     * WhatsApp's own opt-out convention. STOP silences automation and
     * templates for this contact; START undoes it. A person still sees the
     * message and can reply inside the service window.
     */
    private function optOutKeywords(WhatsAppContact $contact, string $body): void
    {
        $word = strtoupper(trim($body));

        if (in_array($word, ['STOP', 'UNSUBSCRIBE'], true) && ! $contact->opted_out_at) {
            $contact->update(['opted_out_at' => now()]);
        } elseif ($word === 'START' && $contact->opted_out_at) {
            $contact->update(['opted_out_at' => null]);
        }
    }

    /** @param  array<string, mixed>  $message */
    private function historyStatus(array $message): string
    {
        $status = strtolower((string) ($message['history_context']['status'] ?? 'sent'));

        return in_array($status, ['sent', 'delivered', 'read', 'failed'], true) ? $status : 'sent';
    }

    private function timestamp(mixed $value): Carbon
    {
        return is_numeric($value) ? Carbon::createFromTimestamp((int) $value) : now();
    }

    private function latest(?Carbon $a, Carbon $b): Carbon
    {
        return $a && $a->greaterThan($b) ? $a : $b;
    }
}
