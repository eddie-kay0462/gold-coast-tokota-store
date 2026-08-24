<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_email_subscribes_successfully(): void
    {
        $response = $this->postJson('/api/v1/newsletter', [
            'email' => 'ama@example.com',
            'source' => 'footer',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'ama@example.com');
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'ama@example.com', 'source' => 'footer']);
    }

    public function test_resubscribing_with_the_same_email_is_idempotent(): void
    {
        NewsletterSubscriber::factory()->create(['email' => 'ama@example.com']);

        $response = $this->postJson('/api/v1/newsletter', ['email' => 'ama@example.com']);

        $response->assertOk();
        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }

    public function test_subscribing_requires_a_valid_email(): void
    {
        $response = $this->postJson('/api/v1/newsletter', ['email' => 'not-an-email']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_source_is_optional(): void
    {
        $response = $this->postJson('/api/v1/newsletter', ['email' => 'kwame@example.com']);

        $response->assertCreated();
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'kwame@example.com', 'source' => null]);
    }
}
