<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\WorkshopSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_session_index_lists_only_upcoming_sessions_with_remaining_capacity(): void
    {
        $past = WorkshopSession::factory()->create(['scheduled_date' => now()->subWeek()->toDateString(), 'capacity' => 8]);
        $upcoming = WorkshopSession::factory()->create(['scheduled_date' => now()->addWeek()->toDateString(), 'capacity' => 8]);
        Booking::factory()->workshop($upcoming)->count(3)->create();

        $response = $this->getJson('/api/v1/workshop-sessions');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.id', $upcoming->id);
        $response->assertJsonPath('data.0.remaining_capacity', 5);
    }

    public function test_workshop_booking_is_pending_when_capacity_available(): void
    {
        $session = WorkshopSession::factory()->create(['capacity' => 2]);

        $response = $this->postJson('/api/v1/bookings', [
            'type' => 'workshop',
            'workshop_session_id' => $session->id,
            'details' => [
                'name' => 'Ama Mensah',
                'email' => 'ama@example.com',
                'phone' => '+233200000000',
                'attendee_count' => 1,
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.workshop_session.id', $session->id);
    }

    public function test_workshop_booking_is_waitlisted_when_session_is_at_capacity(): void
    {
        $session = WorkshopSession::factory()->create(['capacity' => 1]);
        Booking::factory()->workshop($session)->create();

        $response = $this->postJson('/api/v1/bookings', [
            'type' => 'workshop',
            'workshop_session_id' => $session->id,
            'details' => [
                'name' => 'Kwame Owusu',
                'email' => 'kwame@example.com',
                'phone' => '+233200000001',
                'attendee_count' => 1,
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'waitlisted');
    }

    public function test_cancelled_workshop_bookings_do_not_count_against_capacity(): void
    {
        $session = WorkshopSession::factory()->create(['capacity' => 1]);
        Booking::factory()->workshop($session)->cancelled()->create();

        $response = $this->postJson('/api/v1/bookings', [
            'type' => 'workshop',
            'workshop_session_id' => $session->id,
            'details' => [
                'name' => 'Adjoa Boateng',
                'email' => 'adjoa@example.com',
                'phone' => '+233200000002',
                'attendee_count' => 1,
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_workshop_booking_requires_a_workshop_session_id(): void
    {
        $response = $this->postJson('/api/v1/bookings', [
            'type' => 'workshop',
            'details' => [
                'name' => 'Ama Mensah',
                'email' => 'ama@example.com',
                'phone' => '+233200000000',
                'attendee_count' => 1,
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('workshop_session_id');
    }

    public function test_diy_order_is_always_accepted_regardless_of_volume(): void
    {
        Booking::factory()->count(20)->create();

        $response = $this->postJson('/api/v1/bookings', [
            'type' => 'diy_order',
            'details' => [
                'name' => 'Yaw Asante',
                'email' => 'yaw@example.com',
                'phone' => '+233200000003',
                'size' => '42',
                'foot_length' => 27.5,
                'fulfilment' => 'pickup',
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.type', 'diy_order');
    }

    public function test_diy_order_requires_sandal_specs(): void
    {
        $response = $this->postJson('/api/v1/bookings', [
            'type' => 'diy_order',
            'details' => [
                'name' => 'Yaw Asante',
                'email' => 'yaw@example.com',
                'phone' => '+233200000003',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['details.size', 'details.foot_length', 'details.fulfilment']);
    }

    public function test_booking_requires_contact_details(): void
    {
        $response = $this->postJson('/api/v1/bookings', [
            'type' => 'diy_order',
            'details' => [
                'size' => '42',
                'foot_length' => 27.5,
                'fulfilment' => 'pickup',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['details.name', 'details.email', 'details.phone']);
    }

    public function test_booking_can_be_attached_to_an_authenticated_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/v1/bookings', [
            'type' => 'diy_order',
            'customer_id' => $customer->id,
            'details' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => '+233200000004',
                'size' => '41',
                'foot_length' => 26.0,
                'fulfilment' => 'delivery',
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('bookings', ['customer_id' => $customer->id]);
    }
}
