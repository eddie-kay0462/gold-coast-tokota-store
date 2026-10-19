<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveQuickReplyRequest;
use App\Http\Requests\Admin\UpdateWhatsAppAutomationRequest;
use App\Models\QuickReply;
use App\Models\SiteSetting;
use App\Models\WhatsAppConversation;
use App\Services\WhatsApp\AutoReplies;
use App\Services\WhatsApp\QuickReplyRenderer;
use App\Support\BusinessHours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The inbox's semi-automation: the automatic replies' settings, and the
 * team's quick replies.
 */
class InboxAutomationController extends Controller
{
    public function show(AutoReplies $replies): JsonResponse
    {
        return response()->json(['data' => $this->settings($replies)]);
    }

    public function update(UpdateWhatsAppAutomationRequest $request, AutoReplies $replies): JsonResponse
    {
        $data = $request->validated();
        $changes = array_filter([
            'whatsapp_auto_greeting' => $data['auto_greeting'] ?? null,
            'whatsapp_after_hours_enabled' => $data['after_hours_enabled'] ?? null,
            'whatsapp_hours' => isset($data['hours']) ? BusinessHours::from($data['hours'])->toArray() : null,
        ], fn ($v) => $v !== null);

        if (array_key_exists('after_hours_message', $data)) {
            $changes['whatsapp_after_hours_message'] = $data['after_hours_message'] ?: null;
        }

        SiteSetting::current()->update($changes);

        return response()->json(['data' => $this->settings($replies)]);
    }

    public function quickReplies(): JsonResponse
    {
        return response()->json(['data' => QuickReply::query()->orderBy('sort_order')->orderBy('title')->get()
            ->map(fn (QuickReply $r) => $this->reply($r))->values()]);
    }

    public function storeQuickReply(SaveQuickReplyRequest $request): JsonResponse
    {
        $reply = QuickReply::create([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order', (int) QuickReply::query()->max('sort_order') + 1),
            'created_by_admin_id' => $request->user('admin')->id,
        ]);

        return response()->json(['data' => $this->reply($reply)], 201);
    }

    public function updateQuickReply(SaveQuickReplyRequest $request, QuickReply $quickReply): JsonResponse
    {
        $quickReply->update($request->validated());

        return response()->json(['data' => $this->reply($quickReply)]);
    }

    public function destroyQuickReply(QuickReply $quickReply): JsonResponse
    {
        $quickReply->delete();

        return response()->json(null, 204);
    }

    /** A quick reply with its slots filled for one chat, ready to edit and send. */
    public function render(Request $request, QuickReply $quickReply, QuickReplyRenderer $renderer): JsonResponse
    {
        $request->validate(['thread' => ['required', 'integer', 'exists:whatsapp_conversations,id']]);

        return response()->json(['data' => $renderer->render(
            $quickReply->body,
            WhatsAppConversation::query()->findOrFail($request->integer('thread')),
        )]);
    }

    /** @return array<string, mixed> */
    private function settings(AutoReplies $replies): array
    {
        $settings = SiteSetting::current();
        $hours = BusinessHours::from($settings->whatsapp_hours);

        return [
            'auto_greeting' => (bool) $settings->whatsapp_auto_greeting,
            'greeting' => $settings->whatsapp_greeting,
            'after_hours_enabled' => (bool) $settings->whatsapp_after_hours_enabled,
            'after_hours_message' => $settings->whatsapp_after_hours_message,
            // What actually goes out when the message above is left blank.
            'after_hours_preview' => $replies->afterHoursText($settings),
            'hours' => $hours->toArray(),
            'hours_label' => $hours->describe(),
            'open_now' => $hours->isOpen(now()),
            'slots' => collect(QuickReplyRenderer::SLOTS)->map(fn ($s, $key) => ['slot' => "{{$key}}", 'description' => $s[1]])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function reply(QuickReply $r): array
    {
        return ['id' => $r->id, 'title' => $r->title, 'shortcut' => $r->shortcut, 'body' => $r->body, 'sort_order' => $r->sort_order];
    }
}
