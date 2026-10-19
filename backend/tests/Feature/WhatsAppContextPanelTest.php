<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\FxRate;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingZoneRate;
use App\Models\SiteSetting;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WorkshopSession;
use App\Models\WorkshopType;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The inbox's context panel: customer, stock, events and info lookups. */
class WhatsAppContextPanelTest extends TestCase
{
    use RefreshDatabase;

    private function thread(string $waId = '233245551234', ?int $customerId = null): WhatsAppConversation
    {
        $contact = WhatsAppContact::create(['wa_id' => $waId, 'phone_e164' => '+'.$waId, 'profile_name' => 'Ama', 'customer_id' => $customerId]);

        return WhatsAppConversation::create(['contact_id' => $contact->id, 'last_inbound_at' => now()]);
    }

    private function as(string $role): static
    {
        return $this->actingAs(AdminUser::factory()->create([
            'role' => $role,
            'access_expires_at' => $role === 'intern' ? now()->addMonth() : null,
        ]), 'admin');
    }

    // --- customer ---------------------------------------------------------

    public function test_the_customer_tab_finds_their_account_orders_and_guest_orders_by_phone(): void
    {
        $customer = Customer::factory()->create(['phone' => '024 555 1234']);
        $thread = $this->thread(customerId: $customer->id);

        $account = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'paid']);
        OrderItem::factory()->create(['order_id' => $account->id, 'product_name' => 'Kentehene', 'variant_label' => '42', 'quantity' => 1]);
        // Placed as a guest, with the number written the local way.
        $guest = Order::factory()->create(['shipping_address' => ['phone' => '0245551234', 'country' => 'GH']]);
        // Someone else whose number merely ends in the same four digits.
        Order::factory()->create(['shipping_address' => ['phone' => '0209991234', 'country' => 'GH']]);
        $thread->update(['linked_order_id' => $guest->id]);

        Booking::factory()->create(['details' => ['name' => 'Ama', 'email' => 'a@example.com', 'phone' => '+233 24 555 1234']]);

        $response = $this->as('staff')->getJson("/api/v1/admin/inbox/threads/{$thread->id}/context")
            ->assertOk()
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonCount(2, 'data.orders')
            ->assertJsonCount(1, 'data.bookings');

        $orders = collect($response->json('data.orders'))->keyBy('reference');
        $this->assertSame(['1 × Kentehene (42)'], $orders[$account->reference]['items']);
        $this->assertTrue($orders[$guest->reference]['is_linked']);
    }

    public function test_a_thread_with_no_customer_record_still_shows_the_contact(): void
    {
        $thread = $this->thread();

        $this->as('intern')->getJson("/api/v1/admin/inbox/threads/{$thread->id}/context")
            ->assertOk()
            ->assertJsonPath('data.customer', null)
            ->assertJsonPath('data.contact.phone', '+233245551234');
    }

    // --- stock --------------------------------------------------------------

    public function test_stock_search_shows_sellable_stock_per_variant_in_both_currencies(): void
    {
        FxRate::factory()->create(['rate' => 0.08]);
        $product = Product::factory()->create(['name' => 'Kentehene Collection', 'base_price_ghs' => 45_000, 'is_active' => true]);
        $item42 = InventoryItem::factory()->create(['product_id' => $product->id, 'variant_attributes' => ['size' => '42', 'colour' => 'Tan'], 'quantity_available' => 3, 'low_stock_threshold' => 1]);
        InventoryItem::factory()->create(['product_id' => $product->id, 'variant_attributes' => ['size' => '43', 'colour' => 'Tan'], 'quantity_available' => 0]);
        Product::factory()->create(['name' => 'Flavourful Cross Slippers']);

        // A WhatsApp customer waiting on payment holds two of the three.
        app(InventoryReservationService::class)->reserve($item42, 2, now()->addDay(), Order::factory()->create(['channel' => 'whatsapp']));

        $this->as('intern')->getJson('/api/v1/admin/inbox/lookup/stock?q=kente')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kentehene Collection')
            ->assertJsonPath('data.0.price', ['amount' => 45_000, 'currency' => 'GHS'])
            ->assertJsonPath('data.0.price_usd', ['amount' => 3_600, 'currency' => 'USD'])
            ->assertJsonPath('data.0.sellable_total', 1)
            ->assertJsonPath('data.0.variants.0.label', 'Size 42 · Tan')
            ->assertJsonPath('data.0.variants.0.sellable', 1)
            ->assertJsonPath('data.0.variants.0.held', 2)
            ->assertJsonPath('data.0.variants.1.sellable', 0)
            ->assertJsonPath('data.0.url', rtrim(config('app.storefront_url'), '/').'/shop/'.$product->slug);
    }

    public function test_an_inactive_product_is_shown_and_flagged_after_active_ones(): void
    {
        Product::factory()->create(['name' => 'Old Kente Slide', 'is_active' => false]);
        Product::factory()->create(['name' => 'Kentehene', 'is_active' => true]);

        $this->as('staff')->getJson('/api/v1/admin/inbox/lookup/stock?q=kente')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Kentehene')
            ->assertJsonPath('data.1.is_active', false);
    }

    // --- events ---------------------------------------------------------------

    public function test_events_list_upcoming_sessions_with_seats_left_and_the_waitlist(): void
    {
        $type = WorkshopType::factory()->create(['name' => 'Sandal Sip & Paint']);
        $soon = WorkshopSession::factory()->create(['workshop_type_id' => $type->id, 'scheduled_date' => now()->addDays(3)->toDateString(), 'capacity' => 3]);
        WorkshopSession::factory()->create(['workshop_type_id' => $type->id, 'scheduled_date' => now()->subDay()->toDateString()]);
        Booking::factory()->count(3)->create(['type' => 'workshop', 'workshop_session_id' => $soon->id, 'status' => 'confirmed']);
        Booking::factory()->create(['type' => 'workshop', 'workshop_session_id' => $soon->id, 'status' => 'waitlisted']);
        Booking::factory()->create(['type' => 'workshop', 'workshop_session_id' => $soon->id, 'status' => 'cancelled']);
        WorkshopType::factory()->create(['name' => 'Corporate Team Building', 'requires_appointment' => true, 'capacity' => 30]);
        BlogPost::factory()->create(['title' => 'Sip & Paint returns this December']);
        BlogPost::factory()->create(['title' => 'Draft post', 'is_published' => false]);

        $this->as('staff')->getJson('/api/v1/admin/inbox/lookup/events')
            ->assertOk()
            ->assertJsonCount(1, 'data.sessions')
            ->assertJsonPath('data.sessions.0.experience', 'Sandal Sip & Paint')
            ->assertJsonPath('data.sessions.0.remaining', 0)
            ->assertJsonPath('data.sessions.0.waitlisted', 1)
            ->assertJsonPath('data.by_appointment.0.name', 'Corporate Team Building')
            ->assertJsonCount(1, 'data.posts')
            ->assertJsonPath('data.posts.0.title', 'Sip & Paint returns this December');
    }

    // --- info -------------------------------------------------------------------

    public function test_info_quotes_the_published_rules_word_for_word(): void
    {
        SiteSetting::current()->update([
            'business_hours' => 'Monday – Saturday: 9:00 AM – 5:00 PM (GMT)',
            'diy_turnaround_tiers' => [
                ['id' => 'custom', 'label' => 'Custom sandal order', 'estimate' => '3–5 business days', 'sort_order' => 2],
                ['id' => 'standard', 'label' => 'Standard sandal order', 'estimate' => '1–2 business days', 'sort_order' => 1],
            ],
        ]);
        ShippingZoneRate::factory()->create(['region' => 'Ashanti', 'amount_ghs' => 5_000]);

        $response = $this->as('intern')->getJson('/api/v1/admin/inbox/lookup/info')
            ->assertOk()
            ->assertJsonPath('data.business_hours', 'Monday – Saturday: 9:00 AM – 5:00 PM (GMT)')
            ->assertJsonPath('data.turnaround.0.label', 'Standard sandal order')
            ->assertJsonPath('data.rates.regions.0', ['region' => 'Ashanti', 'rate' => ['amount' => 5_000, 'currency' => 'GHS']]);

        $returns = collect($response->json('data.returns'))->keyBy('label');
        $this->assertSame('7 days from receiving the order', $returns['Return window']['text']);
        $this->assertSame('Approved refunds: 7–14 business days', $returns['Refund processing']['text']);
        $this->assertContains('MTN MoMo', $response->json('data.payment_methods'));
        $this->assertSame('Orders are processed within 48 hours after payment confirmation.', $response->json('data.delivery.0.text'));
    }

    public function test_the_panel_is_closed_to_guests(): void
    {
        $thread = $this->thread();

        $this->getJson("/api/v1/admin/inbox/threads/{$thread->id}/context")->assertUnauthorized();
        $this->getJson('/api/v1/admin/inbox/lookup/stock')->assertUnauthorized();
        $this->getJson('/api/v1/admin/inbox/lookup/events')->assertUnauthorized();
        $this->getJson('/api/v1/admin/inbox/lookup/info')->assertUnauthorized();
    }
}
