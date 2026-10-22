<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Booking;
use App\Models\Order;
use App\Models\QuickReply;
use App\Models\SiteSetting;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Models\WorkshopSession;
use App\Models\WorkshopType;
use App\Notifications\NotificationRecipient;
use App\Notifications\TransactionalMessages;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\BusinessHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Automatic replies, WhatsApp status updates and quick replies. */
class WhatsAppAutomationTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test-app-secret';

    private const CUSTOMER = '233245551234';

    /** A Tuesday, mid-morning in Accra: open. */
    private const OPEN = '2026-10-20 10:30:00';

    /** A Sunday: closed all day. */
    private const CLOSED = '2026-10-25 10:30:00';

    private const GREETING = "Welcome to Gold Coast Tokota!\n\nThank you for contacting us.";

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.app_secret' => self::APP_SECRET,
            'whatsapp.phone_number_id' => 'PNID',
            'whatsapp.access_token' => null,
        ]);
        $this->app->forgetInstance(WhatsAppClient::class);
        Http::preventStrayRequests();
        SiteSetting::current()->update(['whatsapp_greeting' => self::GREETING, 'whatsapp_hours' => BusinessHours::DEFAULT]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $when): void
    {
        Carbon::setTestNow(Carbon::parse($when, 'Africa/Accra'));
    }

    private function inbound(string $text = 'Hello'): void
    {
        $body = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
                'metadata' => ['phone_number_id' => 'PNID'],
                'contacts' => [['profile' => ['name' => 'Ama Mensah'], 'wa_id' => self::CUSTOMER]],
                'messages' => [[
                    'from' => self::CUSTOMER, 'id' => 'wamid.'.uniqid('', true), 'timestamp' => (string) now()->timestamp,
                    'type' => 'text', 'text' => ['body' => $text],
                ]],
            ]]]]],
        ]);

        $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, self::APP_SECRET),
        ], $body)->assertOk();
    }

    /** @return list<string|null> what automation sent, oldest first */
    private function automatic(): array
    {
        return WhatsAppMessage::query()->where('source', 'automation')->orderBy('id')->get()
            ->map(fn (WhatsAppMessage $m) => $m->meta['automation'] ?? null)->all();
    }

    private function thread(): WhatsAppConversation
    {
        return WhatsAppConversation::query()->sole();
    }

    private function admin(string $role = 'admin'): AdminUser
    {
        return AdminUser::factory()->create(['role' => $role]);
    }

    // --- automatic replies -------------------------------------------------

    public function test_nothing_is_sent_automatically_until_an_admin_switches_it_on(): void
    {
        $this->at(self::CLOSED);

        $this->inbound();

        $this->assertSame([], $this->automatic());
    }

    public function test_the_greeting_goes_on_a_first_message_and_again_after_thirty_days_of_silence(): void
    {
        SiteSetting::current()->update(['whatsapp_auto_greeting' => true]);
        $this->at(self::OPEN);

        $this->inbound('Hello');
        $this->inbound('Do you have size 42?');
        $this->assertSame(['greeting'], $this->automatic());
        $this->assertSame(self::GREETING, WhatsAppMessage::query()->where('source', 'automation')->sole()->body);

        $this->at('2026-11-24 10:30:00'); // 35 days on, a Tuesday
        $this->inbound('Hi again');
        $this->assertSame(['greeting', 'greeting'], $this->automatic());
    }

    public function test_the_after_hours_note_goes_once_per_closed_spell(): void
    {
        SiteSetting::current()->update(['whatsapp_after_hours_enabled' => true]);

        $this->at('2026-10-24 19:00:00'); // Saturday evening: closed until Monday
        $this->inbound('Are you open tomorrow?');
        $this->at(self::CLOSED); // Sunday, same closed spell
        $this->inbound('Hello?');
        $this->assertSame(['after_hours'], $this->automatic());

        $note = WhatsAppMessage::query()->where('source', 'automation')->sole()->body;
        $this->assertStringContainsString('Monday – Saturday, 9:00 AM – 5:00 PM (GMT)', $note);

        $this->at('2026-10-26 11:00:00'); // Monday, open: nothing
        $this->inbound('Morning!');
        $this->at('2026-10-26 18:00:00'); // Monday evening: a new closed spell
        $this->inbound('One more thing');
        $this->assertSame(['after_hours', 'after_hours'], $this->automatic());
    }

    public function test_no_after_hours_note_interrupts_a_team_member_still_replying(): void
    {
        SiteSetting::current()->update(['whatsapp_after_hours_enabled' => true]);
        $this->at('2026-10-20 16:50:00');
        $this->inbound('Is the Kentehene in stock?');
        $this->actingAs($this->admin('staff'), 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$this->thread()->id}/messages", ['body' => 'Let me check.'])
            ->assertStatus(202);

        $this->at('2026-10-20 17:05:00'); // closed, but someone is mid-conversation
        $this->inbound('Thanks!');

        $this->assertSame([], $this->automatic());
    }

    public function test_a_first_message_after_hours_gets_the_greeting_only(): void
    {
        SiteSetting::current()->update(['whatsapp_auto_greeting' => true, 'whatsapp_after_hours_enabled' => true]);
        $this->at(self::CLOSED);

        $this->inbound();

        $this->assertSame(['greeting'], $this->automatic());
    }

    public function test_someone_who_replies_stop_gets_nothing_automatic(): void
    {
        SiteSetting::current()->update(['whatsapp_auto_greeting' => true]);
        $this->at(self::OPEN);

        $this->inbound('STOP');

        $this->assertSame([], $this->automatic());
    }

    public function test_only_an_admin_can_change_the_automatic_replies(): void
    {
        $this->actingAs($this->admin('staff'), 'admin')
            ->getJson('/api/v1/admin/inbox/automation')
            ->assertOk()
            ->assertJsonPath('data.auto_greeting', false)
            ->assertJsonPath('data.hours_label', 'Monday – Saturday, 9:00 AM – 5:00 PM (GMT)');

        $this->actingAs($this->admin('staff'), 'admin')
            ->putJson('/api/v1/admin/inbox/automation', ['auto_greeting' => true])
            ->assertForbidden();

        $this->actingAs($this->admin(), 'admin')
            ->putJson('/api/v1/admin/inbox/automation', [
                'auto_greeting' => true,
                'hours' => ['days' => [1, 2, 3, 4, 5], 'open' => '08:30', 'close' => '16:00'],
            ])
            ->assertOk()
            ->assertJsonPath('data.auto_greeting', true)
            ->assertJsonPath('data.hours_label', 'Monday – Friday, 8:30 AM – 4:00 PM (GMT)');

        $this->actingAs($this->admin(), 'admin')
            ->putJson('/api/v1/admin/inbox/automation', ['hours' => ['days' => [1], 'open' => '17:00', 'close' => '09:00']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hours.close');
    }

    // --- status updates on WhatsApp ----------------------------------------

    private function notifyPaid(Order $order): void
    {
        app(NotificationDispatcher::class)->send(
            NotificationRecipient::forOrder($order),
            TransactionalMessages::orderConfirmation($order->load('items')),
        );
    }

    public function test_an_order_update_goes_to_a_customer_who_is_chatting_as_plain_text(): void
    {
        $this->at(self::OPEN);
        $this->inbound('I just paid');
        $order = Order::factory()->create(['status' => 'paid', 'shipping_address' => ['full_name' => 'Ama Mensah', 'phone' => '024 555 1234', 'country' => 'GH']]);

        $this->notifyPaid($order);

        $update = WhatsAppMessage::query()->where('source', 'automation')->sole();
        $this->assertSame('order_placed', $update->meta['automation']);
        $this->assertStringStartsWith("payment received for order {$order->reference}", $update->body);
    }

    public function test_someone_who_never_wrote_on_whatsapp_gets_no_whatsapp_update(): void
    {
        $order = Order::factory()->create(['status' => 'paid', 'shipping_address' => ['phone' => '0209999999', 'country' => 'GH']]);

        $this->notifyPaid($order);

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_outside_the_window_the_update_uses_its_approved_template_or_nothing(): void
    {
        $this->at('2026-10-10 10:30:00');
        $this->inbound('Hello');
        $this->at(self::OPEN); // ten days later: window closed
        $order = Order::factory()->create(['status' => 'paid', 'shipping_address' => ['full_name' => 'Ama Mensah', 'phone' => '+233245551234', 'country' => 'GH']]);

        $this->notifyPaid($order);
        $this->assertSame([], $this->automatic());

        WhatsAppTemplate::create([
            'name' => 'order_confirmed', 'language' => 'en', 'category' => 'utility', 'status' => 'approved',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, we have your payment for order {{2}} ({{3}}).']],
        ]);

        $this->notifyPaid($order);
        $sent = WhatsAppMessage::query()->where('source', 'automation')->sole();
        $this->assertSame('template', $sent->kind);
        $this->assertStringStartsWith("Hi Ama, we have your payment for order {$order->reference} (GH", $sent->body);
    }

    public function test_a_booking_confirmation_reaches_a_customer_who_is_chatting(): void
    {
        $this->at(self::OPEN);
        $this->inbound('Did my booking go through?');
        $booking = Booking::factory()->create(['status' => 'confirmed', 'details' => ['name' => 'Ama', 'email' => 'a@example.com', 'phone' => '0245551234']]);

        app(NotificationDispatcher::class)->send(NotificationRecipient::forBooking($booking), TransactionalMessages::bookingConfirmed($booking));

        $this->assertSame(['booking_confirmed'], $this->automatic());
    }

    // --- quick replies ---------------------------------------------------------

    public function test_quick_replies_are_seeded_and_filled_from_the_chat(): void
    {
        $this->at(self::OPEN);
        $this->inbound();
        $order = Order::factory()->create(['status' => 'shipped', 'shipping_address' => ['full_name' => 'Ama Mensah', 'country' => 'GB']]);
        $this->thread()->update(['linked_order_id' => $order->id]);
        $staff = $this->admin('staff');

        $this->actingAs($staff, 'admin')->getJson('/api/v1/admin/inbox/quick-replies')
            ->assertOk()->assertJsonCount(6, 'data')->assertJsonPath('data.0.shortcut', 'check');

        $render = fn (string $shortcut) => $this->actingAs($staff, 'admin')
            ->postJson('/api/v1/admin/inbox/quick-replies/'.QuickReply::query()->where('shortcut', $shortcut)->value('id').'/render', ['thread' => $this->thread()->id])
            ->assertOk();

        $render('status')->assertJsonPath('data.body', "Your order {$order->reference} is shipped.")->assertJsonPath('data.missing', []);
        $render('check')->assertJsonPath('data.body', 'Thank you for reaching out to Gold Coast Tokota, Ama. Let me check that for you now.');
        $render('delivery')->assertJson(fn ($json) => $json->where('data.body', fn ($b) => str_contains($b, '7–14 business days'))->etc());
        $render('returns')->assertJsonPath('data.body', 'You can return or exchange an item within 7 days of receiving your order.');
    }

    public function test_a_slot_with_nothing_to_fill_it_is_left_visible_and_reported(): void
    {
        $this->at(self::OPEN);
        $this->inbound();

        $this->actingAs(AdminUser::factory()->create(['role' => 'intern', 'access_expires_at' => now()->addMonth()]), 'admin')
            ->postJson('/api/v1/admin/inbox/quick-replies/'.QuickReply::query()->where('shortcut', 'status')->value('id').'/render', ['thread' => $this->thread()->id])
            ->assertOk()
            ->assertJsonPath('data.body', 'Your order [order number] is [order status].')
            ->assertJsonPath('data.missing', ['order.reference', 'order.status']);
    }

    public function test_the_next_workshop_slot_names_a_session_with_places_left(): void
    {
        $this->at(self::OPEN);
        $this->inbound();
        $type = WorkshopType::factory()->create(['name' => 'Sandal Sip & Paint']);
        $full = WorkshopSession::factory()->create(['workshop_type_id' => $type->id, 'scheduled_date' => '2026-10-24', 'scheduled_slot' => '10:00 - 13:00', 'capacity' => 1]);
        Booking::factory()->create(['type' => 'workshop', 'workshop_session_id' => $full->id, 'status' => 'confirmed']);
        WorkshopSession::factory()->create(['workshop_type_id' => $type->id, 'scheduled_date' => '2026-10-31', 'scheduled_slot' => '10:00 - 13:00', 'capacity' => 20]);

        $this->actingAs($this->admin('staff'), 'admin')
            ->postJson('/api/v1/admin/inbox/quick-replies/'.QuickReply::query()->where('shortcut', 'workshop')->value('id').'/render', ['thread' => $this->thread()->id])
            ->assertOk()
            ->assertJsonPath('data.body', 'The next workshop is Sandal Sip & Paint on Saturday 31 October, 10:00 - 13:00 (20 places left).');
    }

    public function test_writing_quick_replies_is_admin_work_and_shortcuts_are_unique(): void
    {
        $this->actingAs($this->admin('staff'), 'admin')
            ->postJson('/api/v1/admin/inbox/quick-replies', ['title' => 'X', 'body' => 'Y'])
            ->assertForbidden();

        $this->actingAs($this->admin(), 'admin')
            ->postJson('/api/v1/admin/inbox/quick-replies', ['title' => 'Sizes', 'shortcut' => '/Sizes', 'body' => 'Here is our size guide.'])
            ->assertCreated()
            ->assertJsonPath('data.shortcut', 'sizes');

        $this->actingAs($this->admin(), 'admin')
            ->postJson('/api/v1/admin/inbox/quick-replies', ['title' => 'Again', 'shortcut' => 'sizes', 'body' => '…'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shortcut');
    }
}
