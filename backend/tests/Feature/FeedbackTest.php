<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Feedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private const VALID = [
        'name' => 'Ama Serwaa',
        'email' => 'ama@example.com',
        'message' => 'The sandals are beautiful and the sizing was spot on.',
    ];

    public function test_a_visitor_can_submit_feedback_without_an_account(): void
    {
        $response = $this->postJson('/api/v1/feedback', self::VALID);

        $response->assertCreated();
        $this->assertDatabaseHas('feedback', [
            'email' => 'ama@example.com',
            'customer_id' => null,
        ]);
    }

    public function test_a_signed_in_customer_is_attached_to_their_feedback(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'web')->postJson('/api/v1/feedback', self::VALID)->assertCreated();

        $this->assertSame($customer->id, Feedback::first()->customer_id);
    }

    /** Never from the body — otherwise anyone can file feedback as anyone. */
    public function test_a_customer_id_in_the_body_is_ignored(): void
    {
        $someoneElse = Customer::factory()->create();

        $this->postJson('/api/v1/feedback', [...self::VALID, 'customer_id' => $someoneElse->id])
            ->assertCreated();

        $this->assertNull(Feedback::first()->customer_id);
    }

    public function test_name_email_and_message_are_all_required(): void
    {
        $this->postJson('/api/v1/feedback', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'message']);
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->postJson('/api/v1/feedback', [...self::VALID, 'email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /** Unauthenticated and free-text: an unbounded field is a way to fill a disk. */
    public function test_an_oversized_message_is_rejected(): void
    {
        $this->postJson('/api/v1/feedback', [...self::VALID, 'message' => str_repeat('a', 5001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_a_rating_outside_one_to_five_is_rejected(): void
    {
        $this->postJson('/api/v1/feedback', [...self::VALID, 'rating' => 6])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }

    // --- admin ----------------------------------------------------------

    public function test_admin_feedback_list_is_closed_to_guests(): void
    {
        $this->getJson('/api/v1/admin/feedback')->assertUnauthorized();
    }

    public function test_staff_can_read_the_feedback_list(): void
    {
        $staff = AdminUser::factory()->create(['role' => 'staff']);
        $this->postJson('/api/v1/feedback', self::VALID)->assertCreated();

        $response = $this->actingAs($staff, 'admin')->getJson('/api/v1/admin/feedback');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Ama Serwaa');
        $response->assertJsonPath('data.0.message', self::VALID['message']);
        // The admin table's column is "submitted at", not "created at".
        $this->assertNotNull($response->json('data.0.submitted_at'));
    }

    public function test_feedback_is_listed_newest_first(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);

        Feedback::create([...self::VALID, 'message' => 'older', 'created_at' => now()->subDay()]);
        Feedback::create([...self::VALID, 'message' => 'newer']);

        $response = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/feedback');

        $response->assertOk();
        $response->assertJsonPath('data.0.message', 'newer');
    }
}
