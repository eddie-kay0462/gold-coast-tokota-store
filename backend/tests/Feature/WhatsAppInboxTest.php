<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\AdminCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppInboxTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-app-secret';

    private const PHONE_NUMBER_ID = '1098765432';

    private const CUSTOMER = '233245551234';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.app_secret' => self::SECRET,
            'whatsapp.verify_token' => 'verify-me',
            'whatsapp.phone_number_id' => self::PHONE_NUMBER_ID,
            'whatsapp.waba_id' => 'WABA1',
            // No access token: LogWhatsAppClient, so nothing reaches Meta
            // unless a test configures the Cloud API on purpose.
            'whatsapp.access_token' => null,
        ]);
        $this->app->forgetInstance(WhatsAppClient::class);
        Http::preventStrayRequests();
    }

    // --- helpers ------------------------------------------------------------

    private function deliver(array $payload, ?string $signature = null)
    {
        $body = json_encode($payload);

        return $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature ?? 'sha256='.hash_hmac('sha256', $body, self::SECRET),
        ], $body);
    }

    private function envelope(string $field, array $value): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA1',
                'changes' => [[
                    'field' => $field,
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '233257534297', 'phone_number_id' => self::PHONE_NUMBER_ID],
                        ...$value,
                    ],
                ]],
            ]],
        ];
    }

    private function inbound(string $text, string $wamid = 'wamid.IN1', ?int $at = null, string $from = self::CUSTOMER): array
    {
        return $this->envelope('messages', [
            'contacts' => [['profile' => ['name' => 'Ama Mensah'], 'wa_id' => $from]],
            'messages' => [[
                'from' => $from,
                'id' => $wamid,
                'timestamp' => (string) ($at ?? now()->timestamp),
                'type' => 'text',
                'text' => ['body' => $text],
            ]],
        ]);
    }

    private function statusUpdate(string $wamid, string $status, array $extra = []): array
    {
        return $this->envelope('messages', [
            'statuses' => [['id' => $wamid, 'status' => $status, 'timestamp' => (string) now()->timestamp, 'recipient_id' => self::CUSTOMER, ...$extra]],
        ]);
    }

    private function thread(): WhatsAppConversation
    {
        return WhatsAppConversation::query()->sole();
    }

    private function staff(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'staff']);
    }

    private function intern(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'intern', 'access_expires_at' => now()->addMonth()]);
    }

    private function approvedTemplate(string $status = 'approved'): WhatsAppTemplate
    {
        return WhatsAppTemplate::create([
            'name' => 'order_update',
            'language' => 'en',
            'category' => 'utility',
            'status' => $status,
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}, your order {{2}} is on its way.']],
        ]);
    }

    // --- webhook: verification and authenticity -------------------------

    public function test_meta_can_verify_the_webhook_url_with_the_right_token(): void
    {
        $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')
            ->assertOk()
            ->assertSeeText('12345');

        $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')
            ->assertForbidden();
    }

    public function test_an_unsigned_or_mis_signed_delivery_is_refused(): void
    {
        $this->deliver($this->inbound('Hello'), signature: '')->assertUnauthorized();
        $this->deliver($this->inbound('Hello'), signature: 'sha256=forged')->assertUnauthorized();

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_with_no_app_secret_nothing_can_be_verified_so_everything_is_refused(): void
    {
        config(['whatsapp.app_secret' => null]);

        $this->deliver($this->inbound('Hello'), signature: 'sha256='.hash_hmac('sha256', '{}', ''))->assertUnauthorized();
    }

    // --- inbound ------------------------------------------------------------

    public function test_a_customer_message_opens_a_thread_and_matches_their_customer_record(): void
    {
        // Typed the local way at checkout; WhatsApp reports it internationally.
        $customer = Customer::factory()->create(['name' => 'Ama Mensah', 'phone' => '024 555 1234']);

        $this->deliver($this->inbound('Do you have Kentehene in 42?'))->assertOk();

        $contact = WhatsAppContact::query()->sole();
        $this->assertSame(self::CUSTOMER, $contact->wa_id);
        $this->assertSame('+233245551234', $contact->phone_e164);
        $this->assertSame($customer->id, $contact->customer_id);

        $thread = $this->thread();
        $this->assertSame(1, $thread->unread_count);
        $this->assertSame('Do you have Kentehene in 42?', $thread->last_message_preview);
        $this->assertTrue($thread->serviceWindowOpen());

        $message = WhatsAppMessage::query()->sole();
        $this->assertSame('inbound', $message->direction);
        $this->assertSame('customer', $message->source);
    }

    public function test_a_replayed_delivery_changes_nothing(): void
    {
        $payload = $this->inbound('Hello');

        $this->deliver($payload)->assertOk();
        $this->deliver($payload)->assertOk();

        $this->assertSame(1, WhatsAppMessage::query()->count());
        $this->assertSame(1, $this->thread()->unread_count);
    }

    public function test_a_message_for_another_number_on_the_account_is_ignored(): void
    {
        $payload = $this->inbound('Hello');
        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = 'someone-else';

        $this->deliver($payload)->assertOk();

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_stop_opts_a_contact_out_and_start_opts_them_back_in(): void
    {
        $this->deliver($this->inbound('STOP', 'wamid.1'));
        $this->assertNotNull(WhatsAppContact::query()->sole()->opted_out_at);

        $this->deliver($this->inbound('start', 'wamid.2'));
        $this->assertNull(WhatsAppContact::query()->sole()->opted_out_at);
    }

    public function test_a_customer_photo_is_copied_into_the_private_bucket(): void
    {
        config(['whatsapp.access_token' => 'token']);
        $this->app->forgetInstance(WhatsAppClient::class);
        Storage::fake(config('filesystems.private_media_disk'));
        Http::fake([
            'graph.facebook.com/*/MEDIA1' => Http::response(['url' => 'https://lookaside.fbsbx.com/media/abc', 'mime_type' => 'image/jpeg']),
            'lookaside.fbsbx.com/*' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->deliver($this->envelope('messages', [
            'contacts' => [['profile' => ['name' => 'Ama'], 'wa_id' => self::CUSTOMER]],
            'messages' => [[
                'from' => self::CUSTOMER, 'id' => 'wamid.IMG', 'timestamp' => (string) now()->timestamp,
                'type' => 'image', 'image' => ['id' => 'MEDIA1', 'mime_type' => 'image/jpeg', 'caption' => 'My foot outline'],
            ]],
        ]))->assertOk();

        $message = WhatsAppMessage::query()->sole();
        $this->assertSame('image', $message->kind);
        $this->assertSame('My foot outline', $message->body);
        $this->assertNotNull($message->media_path);
        Storage::disk(config('filesystems.private_media_disk'))->assertExists($message->media_path);
    }

    // --- statuses -----------------------------------------------------------

    public function test_delivery_status_only_moves_forward_and_a_failure_records_why(): void
    {
        $this->deliver($this->inbound('Hi'));
        $sent = WhatsAppMessage::create([
            'conversation_id' => $this->thread()->id, 'wamid' => 'wamid.OUT', 'direction' => 'outbound',
            'kind' => 'text', 'body' => 'Hello!', 'status' => 'sent', 'source' => 'agent', 'sent_at' => now(),
        ]);

        $this->deliver($this->statusUpdate('wamid.OUT', 'read'));
        $this->deliver($this->statusUpdate('wamid.OUT', 'delivered')); // late, out of order
        $this->assertSame('read', $sent->fresh()->status);

        $this->deliver($this->statusUpdate('wamid.OUT', 'failed', ['errors' => [['code' => 131026, 'title' => 'Message undeliverable']]]));
        $this->assertSame('failed', $sent->fresh()->status);
        $this->assertSame('Message undeliverable', $sent->fresh()->error);
    }

    // --- coexistence --------------------------------------------------------

    public function test_a_reply_typed_on_the_brand_phone_appears_in_the_thread_and_clears_unread(): void
    {
        $this->deliver($this->inbound('Is the shop open Saturday?'));

        $this->deliver($this->envelope('smb_message_echoes', [
            'message_echoes' => [[
                'from' => '233257534297', 'to' => self::CUSTOMER, 'id' => 'wamid.ECHO1',
                'timestamp' => (string) now()->addMinute()->timestamp, 'type' => 'text',
                'text' => ['body' => 'Yes, 9 to 5.'],
            ]],
        ]))->assertOk();

        $echo = WhatsAppMessage::query()->where('wamid', 'wamid.ECHO1')->sole();
        $this->assertSame('outbound', $echo->direction);
        $this->assertSame('phone_app', $echo->source);
        $this->assertSame(0, $this->thread()->unread_count);
        $this->assertSame('Yes, 9 to 5.', $this->thread()->last_message_preview);
    }

    public function test_history_imported_at_onboarding_is_stored_without_marking_anything_unread(): void
    {
        $this->deliver($this->envelope('history', [
            'history' => [[
                'metadata' => ['phase' => 0, 'chunk_order' => 1, 'progress' => 100],
                'threads' => [[
                    'id' => self::CUSTOMER,
                    'messages' => [
                        ['from' => self::CUSTOMER, 'id' => 'wamid.H1', 'timestamp' => (string) now()->subDays(3)->timestamp, 'type' => 'text', 'text' => ['body' => 'Do you ship to Kumasi?'], 'history_context' => ['status' => 'READ']],
                        ['from' => '233257534297', 'to' => self::CUSTOMER, 'id' => 'wamid.H2', 'timestamp' => (string) now()->subDays(3)->addHour()->timestamp, 'type' => 'text', 'text' => ['body' => 'We do.'], 'history_context' => ['status' => 'READ']],
                    ],
                ]],
            ]],
        ]))->assertOk();

        $this->assertSame(['inbound', 'outbound'], WhatsAppMessage::query()->orderBy('sent_at')->pluck('direction')->all());
        $this->assertSame(['history'], WhatsAppMessage::query()->distinct()->pluck('source')->all());
        $this->assertSame(0, $this->thread()->unread_count);
        // Three days old: the window to reply freely has long closed.
        $this->assertFalse($this->thread()->serviceWindowOpen());
    }

    public function test_contact_names_saved_on_the_phone_replace_profile_names(): void
    {
        $this->deliver($this->inbound('Hi'));

        $this->deliver($this->envelope('smb_app_state_sync', [
            'state_sync' => [[
                'type' => 'contact', 'action' => 'add',
                'contact' => ['full_name' => 'Ama (Osu shop)', 'phone_number' => '+233 24 555 1234'],
                'metadata' => ['timestamp' => (string) now()->timestamp],
            ]],
        ]))->assertOk();

        $this->assertSame('Ama (Osu shop)', WhatsAppContact::query()->sole()->profile_name);
    }

    public function test_a_template_status_change_from_meta_is_mirrored(): void
    {
        $this->approvedTemplate('pending');

        $this->deliver($this->envelope('message_template_status_update', [
            'event' => 'REJECTED', 'message_template_id' => 99, 'message_template_name' => 'order_update',
            'message_template_language' => 'en', 'reason' => 'INCORRECT_CATEGORY',
        ]))->assertOk();

        $template = WhatsAppTemplate::query()->sole();
        $this->assertSame('rejected', $template->status);
        $this->assertSame('INCORRECT_CATEGORY', $template->rejection_reason);
    }

    // --- admin: reading ------------------------------------------------------

    public function test_the_thread_list_has_the_shape_the_admin_inbox_reads(): void
    {
        $this->deliver($this->inbound('Do you have Kentehene in 42?'));

        $this->actingAs($this->intern(), 'admin')
            ->getJson('/api/v1/admin/inbox/threads')
            ->assertOk()
            ->assertJsonPath('data.0.contact_name', 'Ama Mensah')
            ->assertJsonPath('data.0.contact_phone', '+233245551234')
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.topic', 'general')
            ->assertJsonPath('data.0.is_archived', false)
            ->assertJsonPath('meta.unread_total', 1)
            ->assertJsonPath('meta.connected', false)
            ->assertJsonStructure(['data' => [['id', 'session_expires_at', 'last_message_at', 'linked_order_id', 'assigned_to_admin_id', 'opted_out']]]);
    }

    public function test_the_inbox_is_closed_to_guests(): void
    {
        $this->getJson('/api/v1/admin/inbox/threads')->assertUnauthorized();
        $this->getJson('/api/v1/admin/inbox/messages?thread=1')->assertUnauthorized();
    }

    public function test_the_open_thread_polls_for_only_what_is_new(): void
    {
        $this->deliver($this->inbound('First', 'wamid.1', now()->subMinutes(2)->timestamp));
        $this->deliver($this->inbound('Second', 'wamid.2', now()->subMinute()->timestamp));
        $first = WhatsAppMessage::query()->where('wamid', 'wamid.1')->sole();
        $staff = $this->staff();
        $thread = $this->thread()->id;

        $this->actingAs($staff, 'admin')->getJson("/api/v1/admin/inbox/messages?thread={$thread}")
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.body', 'First');

        $this->actingAs($staff, 'admin')->getJson("/api/v1/admin/inbox/messages?thread={$thread}&after={$first->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'Second');
    }

    public function test_opening_a_thread_marks_it_read(): void
    {
        $this->deliver($this->inbound('Hello'));

        $this->actingAs($this->intern(), 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$this->thread()->id}/read")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    // --- admin: sending ------------------------------------------------------

    public function test_staff_can_reply_inside_the_window_and_the_log_client_marks_it_simulated(): void
    {
        $this->deliver($this->inbound('Do you have size 42?'));
        $staff = $this->staff();

        $this->actingAs($staff, 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$this->thread()->id}/messages", ['body' => 'Yes, we do.'])
            ->assertStatus(202)
            ->assertJsonPath('data.direction', 'outbound');

        $reply = WhatsAppMessage::query()->where('direction', 'outbound')->sole();
        $this->assertSame('sent', $reply->status);
        $this->assertSame('log', $reply->provider);
        $this->assertSame($staff->id, $reply->sent_by_admin_id);
        $this->assertSame(0, $this->thread()->unread_count);
    }

    public function test_a_free_form_reply_outside_the_window_is_refused_in_plain_words(): void
    {
        $this->deliver($this->inbound('Hello', 'wamid.old', now()->subHours(25)->timestamp));

        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$this->thread()->id}/messages", ['body' => 'Sorry for the wait!'])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'service_window_closed');

        $this->assertSame(0, WhatsAppMessage::query()->where('direction', 'outbound')->count());
    }

    public function test_an_approved_template_can_be_sent_outside_the_window_with_its_values_filled(): void
    {
        $this->deliver($this->inbound('Hello', 'wamid.old', now()->subDays(2)->timestamp));
        $template = $this->approvedTemplate();

        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$this->thread()->id}/messages", [
                'template_id' => $template->id, 'parameters' => ['Ama', 'GCT-ABC123'],
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.body', 'Hello Ama, your order GCT-ABC123 is on its way.')
            ->assertJsonPath('data.template_name', 'order_update');
    }

    public function test_an_unapproved_template_or_an_opted_out_customer_is_refused(): void
    {
        $this->deliver($this->inbound('STOP'));
        $staff = $this->staff();
        $thread = $this->thread()->id;

        $this->actingAs($staff, 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$thread}/messages", ['template_id' => $this->approvedTemplate('pending')->id])
            ->assertUnprocessable()->assertJsonPath('reason', 'template_not_approved');

        WhatsAppTemplate::query()->update(['status' => 'approved']);

        $this->actingAs($staff, 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$thread}/messages", ['template_id' => WhatsAppTemplate::query()->value('id'), 'parameters' => ['Ama', 'X']])
            ->assertUnprocessable()->assertJsonPath('reason', 'opted_out');
    }

    public function test_an_intern_drafts_and_a_staff_member_sends_it(): void
    {
        $this->deliver($this->inbound('Can I book Sip & Paint for 4?'));
        $intern = $this->intern();
        $staff = $this->staff();
        $thread = $this->thread()->id;

        $this->actingAs($intern, 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$thread}/messages", ['body' => 'Yes!'])
            ->assertForbidden();

        $draft = $this->actingAs($intern, 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$thread}/drafts", ['body' => 'Yes, Saturday has space.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        $this->actingAs($staff, 'admin')
            ->postJson("/api/v1/admin/inbox/messages/{$draft}/send", ['body' => 'Yes, Saturday 10am has space for four.'])
            ->assertStatus(202);

        $sent = WhatsAppMessage::query()->findOrFail($draft);
        $this->assertSame('sent', $sent->status);
        $this->assertSame('Yes, Saturday 10am has space for four.', $sent->body);
        $this->assertSame($staff->id, $sent->sent_by_admin_id);
        $this->assertSame($intern->name, $sent->meta['drafted_by']['name']);

        $this->actingAs($staff, 'admin')
            ->postJson("/api/v1/admin/inbox/messages/{$draft}/send")
            ->assertUnprocessable()->assertJsonPath('reason', 'not_a_draft');
    }

    public function test_an_intern_can_discard_only_their_own_draft(): void
    {
        $this->deliver($this->inbound('Hi'));
        $thread = $this->thread()->id;
        $mine = $this->intern();
        $other = $this->intern();

        $draft = $this->actingAs($mine, 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$thread}/drafts", ['body' => 'Hello'])->json('data.id');

        $this->actingAs($other, 'admin')->deleteJson("/api/v1/admin/inbox/messages/{$draft}")->assertForbidden();
        $this->actingAs($mine, 'admin')->deleteJson("/api/v1/admin/inbox/messages/{$draft}")->assertNoContent();
    }

    public function test_staff_can_assign_archive_and_link_an_order(): void
    {
        $this->deliver($this->inbound('Where is my order?'));
        $staff = $this->staff();
        $order = Order::factory()->create();

        $this->actingAs($staff, 'admin')
            ->patchJson("/api/v1/admin/inbox/threads/{$this->thread()->id}", [
                'assigned_admin_id' => $staff->id, 'topic' => 'order', 'linked_order_reference' => $order->reference, 'status' => 'closed',
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to_admin_id', $staff->id)
            ->assertJsonPath('data.linked_order_reference', $order->reference)
            ->assertJsonPath('data.is_archived', true);

        // The customer writing again reopens it.
        $this->deliver($this->inbound('Hello?', 'wamid.2'));
        $this->assertSame('open', $this->thread()->status);
    }

    // --- the Cloud API itself ---------------------------------------------

    public function test_a_reply_goes_to_the_cloud_api_and_keeps_whatsapps_message_id(): void
    {
        config(['whatsapp.access_token' => 'token']);
        $this->app->forgetInstance(WhatsAppClient::class);
        Http::fake([
            'graph.facebook.com/*/'.self::PHONE_NUMBER_ID.'/messages' => Http::response(['messages' => [['id' => 'wamid.FROM_META']]]),
        ]);
        $this->deliver($this->inbound('Hi'));

        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$this->thread()->id}/messages", ['body' => 'Akwaaba!'])
            ->assertStatus(202);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token')
            && $request['to'] === self::CUSTOMER
            && $request['type'] === 'text'
            && $request['text']['body'] === 'Akwaaba!');

        $reply = WhatsAppMessage::query()->where('direction', 'outbound')->sole();
        $this->assertSame('wamid.FROM_META', $reply->wamid);
        $this->assertSame('cloud_api', $reply->provider);
    }

    public function test_a_permanent_cloud_api_error_fails_the_message_with_the_reason(): void
    {
        config(['whatsapp.access_token' => 'token']);
        $this->app->forgetInstance(WhatsAppClient::class);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['code' => 131026, 'message' => 'Message undeliverable', 'error_data' => ['details' => 'Recipient is not on WhatsApp']]], 400),
        ]);
        $this->deliver($this->inbound('Hi'));

        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$this->thread()->id}/messages", ['body' => 'Hello'])
            ->assertStatus(202);

        $reply = WhatsAppMessage::query()->where('direction', 'outbound')->sole();
        $this->assertSame('failed', $reply->status);
        $this->assertSame('Recipient is not on WhatsApp', $reply->error);
    }

    public function test_templates_are_synced_from_the_business_account(): void
    {
        config(['whatsapp.access_token' => 'token']);
        $this->app->forgetInstance(WhatsAppClient::class);
        Http::fake([
            'graph.facebook.com/*/WABA1/message_templates*' => Http::response(['data' => [[
                'id' => '555', 'name' => 'payment_link', 'language' => 'en', 'status' => 'APPROVED', 'category' => 'UTILITY',
                'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, pay for order {{2}} here.']],
            ]]]),
        ]);
        $admin = AdminUser::factory()->create(['role' => 'admin']);

        $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/inbox/templates/sync')->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->postJson('/api/v1/admin/inbox/templates/sync')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'payment_link')
            ->assertJsonPath('data.0.status', 'approved')
            ->assertJsonPath('data.0.variables', ['{{1}}', '{{2}}']);
    }

    // --- capabilities ---------------------------------------------------------

    public function test_the_new_capabilities_follow_section_18(): void
    {
        $this->assertTrue(AdminCapability::allows('super_admin', 'settings.integrations'));
        $this->assertFalse(AdminCapability::allows('admin', 'settings.integrations'));
        $this->assertTrue(AdminCapability::allows('admin', 'inbox.automation'));
        $this->assertFalse(AdminCapability::allows('staff', 'inbox.automation'));
        $this->assertTrue(AdminCapability::allows('staff', 'orders.create'));
        $this->assertFalse(AdminCapability::allows('intern', 'orders.create'));
    }

    public function test_the_settings_screen_reports_whether_the_cloud_api_is_connected(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/settings/whatsapp')
            ->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.webhook_signing_configured', true)
            ->assertJsonPath('data.webhook_url', route('api.v1.webhooks.whatsapp.receive'));

        config(['whatsapp.access_token' => 'token']);

        $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/settings/whatsapp')
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.integration', 'cloud_api');
    }
}
