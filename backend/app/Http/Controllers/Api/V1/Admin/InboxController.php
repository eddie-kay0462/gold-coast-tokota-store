<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendWhatsAppMessageRequest;
use App\Http\Requests\Admin\StoreWhatsAppDraftRequest;
use App\Http\Requests\Admin\UpdateWhatsAppThreadRequest;
use App\Http\Resources\Admin\ChatMessageResource;
use App\Http\Resources\Admin\ChatThreadResource;
use App\Http\Resources\Admin\MessageTemplateResource;
use App\Models\Order;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\CloudApiClient;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppOutbox;
use App\Support\AdminCapability;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The admin WhatsApp inbox. The three read paths are the ones
 * admin/pages/inbox.vue has always called (`threads`, `messages`,
 * `templates`), so the page switches from fixtures to live data on its own.
 *
 * The admin app polls these (no WebSockets, per the README): the thread
 * list every 15 s and the open thread every 5 s, asking only for what is new
 * via `?since=` and `?after=`.
 */
class InboxController extends Controller
{
    public function __construct(private readonly WhatsAppOutbox $outbox) {}

    public function threads(Request $request): AnonymousResourceCollection
    {
        $threads = WhatsAppConversation::query()
            ->with(['contact.customer:id,name', 'linkedOrder:id,reference', 'assignedAdmin:id,name'])
            ->when(
                in_array($request->query('status'), ['open', 'closed'], true),
                fn ($q) => $q->where('status', $request->query('status')),
            )
            ->when($request->query('assigned') === 'me', fn ($q) => $q->where('assigned_admin_id', $request->user('admin')->id))
            ->when($request->query('assigned') === 'none', fn ($q) => $q->whereNull('assigned_admin_id'))
            ->when($request->boolean('unread'), fn ($q) => $q->where('unread_count', '>', 0))
            ->when($request->filled('since'), fn ($q) => $q->where('updated_at', '>', $request->date('since')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.mb_strtolower(trim((string) $request->string('q'))).'%';
                $digits = preg_replace('/\D+/', '', (string) $request->string('q'));

                $q->where(function ($scoped) use ($term, $digits) {
                    $scoped->whereRaw('LOWER(last_message_preview) LIKE ?', [$term])
                        ->orWhereHas('contact', function ($contact) use ($term, $digits) {
                            $contact->whereRaw('LOWER(profile_name) LIKE ?', [$term])
                                ->when($digits !== '', fn ($c) => $c->orWhere('wa_id', 'like', "%{$digits}%"))
                                ->orWhereHas('customer', fn ($customer) => $customer->whereRaw('LOWER(name) LIKE ?', [$term]));
                        });
                });
            })
            // Threads with nothing in them yet sort as new.
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate(max(1, min($request->integer('per_page', 100), 200)))
            ->withQueryString();

        return ChatThreadResource::collection($threads)->additional([
            'meta' => [
                'unread_total' => (int) WhatsAppConversation::query()->where('status', 'open')->sum('unread_count'),
                // Whether replies actually reach WhatsApp. Here rather than on
                // /settings/whatsapp so everyone at the inbox sees it, interns
                // included, who cannot open settings.
                'connected' => CloudApiClient::isConfigured(),
            ],
        ]);
    }

    /**
     * One thread's messages, oldest first. `?after=<message id>` returns only
     * newer ones, which is what the 5-second poll asks for. Without it, the
     * latest 200, enough for any real conversation and a bound on the rest.
     * `?changed_since=` also returns older messages whose delivery status has
     * moved on, so the ticks update.
     */
    public function messages(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $request->validate([
            'thread' => ['required', 'integer'],
            'after' => ['nullable', 'integer'],
            'changed_since' => ['nullable', 'date'],
        ]);

        $query = WhatsAppMessage::query()
            ->with('sentBy:id,name')
            ->where('conversation_id', $request->integer('thread'));

        if ($request->filled('after') || $request->filled('changed_since')) {
            $query->where(function ($q) use ($request) {
                $q->when($request->filled('after'), fn ($w) => $w->orWhere('id', '>', $request->integer('after')))
                    ->when($request->filled('changed_since'), fn ($w) => $w->orWhere('updated_at', '>', $request->date('changed_since')));
            });

            $messages = $query->orderBy('sent_at')->orderBy('id')->limit(500)->get();
        } else {
            $messages = $query->orderByDesc('sent_at')->orderByDesc('id')->limit(200)->get()->reverse()->values();
        }

        return ChatMessageResource::collection($messages);
    }

    public function markRead(WhatsAppConversation $thread): ChatThreadResource
    {
        $thread->update(['unread_count' => 0]);

        return new ChatThreadResource($thread->load(['contact.customer:id,name', 'linkedOrder:id,reference', 'assignedAdmin:id,name']));
    }

    public function update(UpdateWhatsAppThreadRequest $request, WhatsAppConversation $thread): ChatThreadResource
    {
        $data = $request->validated();

        if (array_key_exists('linked_order_reference', $data)) {
            $data['linked_order_id'] = $data['linked_order_reference']
                ? Order::query()->where('reference', $data['linked_order_reference'])->value('id')
                : null;
            unset($data['linked_order_reference']);
        }

        $thread->update($data);

        return new ChatThreadResource($thread->load(['contact.customer:id,name', 'linkedOrder:id,reference', 'assignedAdmin:id,name']));
    }

    public function send(SendWhatsAppMessageRequest $request, WhatsAppConversation $thread): JsonResponse
    {
        $thread->load('contact');
        $admin = $request->user('admin');

        $message = $request->filled('template_id')
            ? $this->outbox->template(
                $thread,
                WhatsAppTemplate::query()->findOrFail($request->integer('template_id')),
                array_values($request->input('parameters', [])),
                $admin,
            )
            : $this->outbox->text($thread, (string) $request->string('body'), $admin);

        return (new ChatMessageResource($message->load('sentBy:id,name')))->response()->setStatusCode(202);
    }

    public function storeDraft(StoreWhatsAppDraftRequest $request, WhatsAppConversation $thread): JsonResponse
    {
        $draft = $this->outbox->draft($thread, (string) $request->string('body'), $request->user('admin'));

        return (new ChatMessageResource($draft->load('sentBy:id,name')))->response()->setStatusCode(201);
    }

    public function sendDraft(Request $request, WhatsAppMessage $message): JsonResponse
    {
        $request->validate(['body' => ['nullable', 'string', 'max:4096']]);

        $sent = $this->outbox->sendDraft($message->load('conversation.contact'), $request->user('admin'), $request->input('body'));

        return (new ChatMessageResource($sent->load('sentBy:id,name')))->response()->setStatusCode(202);
    }

    /** An intern may discard their own draft; someone who can send may discard any. */
    public function destroyDraft(Request $request, WhatsAppMessage $message): JsonResponse
    {
        $admin = $request->user('admin');

        abort_if($message->status !== 'draft', 422, 'Only a draft can be discarded.');
        abort_unless(
            $message->sent_by_admin_id === $admin->id || AdminCapability::allows($admin->role, 'inbox.reply'),
            403,
            AdminCapability::denialMessage('inbox.reply', $admin->role),
        );

        $message->delete();

        return response()->json(null, 204);
    }

    /** A customer's photo or document, through a short-lived signed link. */
    public function media(WhatsAppMessage $message): RedirectResponse
    {
        abort_unless($message->media_path, 404);

        return redirect()->away(MediaStorage::privateUrl($message->media_path, 10));
    }

    public function templates(): AnonymousResourceCollection
    {
        return MessageTemplateResource::collection(
            WhatsAppTemplate::query()->orderByRaw("CASE status WHEN 'approved' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")->orderBy('name')->get(),
        );
    }

    /** Refreshes the local copy of the templates registered with Meta. */
    public function syncTemplates(WhatsAppClient $client): AnonymousResourceCollection
    {
        foreach ($client->fetchTemplates() as $remote) {
            if (empty($remote['name']) || empty($remote['language'])) {
                continue;
            }

            WhatsAppTemplate::query()->updateOrCreate(
                ['name' => $remote['name'], 'language' => $remote['language']],
                [
                    'meta_id' => isset($remote['id']) ? (string) $remote['id'] : null,
                    'category' => strtolower((string) ($remote['category'] ?? 'utility')),
                    'status' => strtolower((string) ($remote['status'] ?? 'pending')),
                    'components' => $remote['components'] ?? [],
                    'rejection_reason' => ($remote['rejected_reason'] ?? null) && $remote['rejected_reason'] !== 'NONE' ? $remote['rejected_reason'] : null,
                    'synced_at' => now(),
                ],
            );
        }

        return $this->templates();
    }
}
