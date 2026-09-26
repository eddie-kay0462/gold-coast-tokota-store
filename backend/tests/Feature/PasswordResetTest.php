<?php

namespace Tests\Feature;

use App\Jobs\SendPasswordReset;
use App\Mail\TransactionalMail;
use App\Models\Customer;
use App\Notifications\NotificationRecipient;
use App\Notifications\TransactionalMessages;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sanctum only boots session middleware for requests whose Referer/Origin
     * is a stateful domain, and the reset flow ends at a real sign-in — same
     * reason CustomerAuthTest does this.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'http://localhost:3000');
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create([
            'email' => 'ama@example.com',
            'password' => Hash::make('old-password-123'),
            ...$attributes,
        ]);
    }

    // ------------------------------------------------------------- requesting

    public function test_a_reset_link_is_emailed_to_a_real_account(): void
    {
        Mail::fake();
        $this->customer();

        $this->postJson('/api/v1/forgot-password', ['email' => 'ama@example.com'])->assertOk();

        Mail::assertSent(
            TransactionalMail::class,
            fn (TransactionalMail $mail) => $mail->hasTo('ama@example.com')
                && $mail->message->key === 'password_reset',
        );
    }

    public function test_the_link_points_at_the_storefront_not_the_api(): void
    {
        Mail::fake();
        config(['app.storefront_url' => 'https://goldcoasttokota.store']);
        $this->customer();

        $this->postJson('/api/v1/forgot-password', ['email' => 'ama@example.com'])->assertOk();

        Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail) {
            $url = $mail->message->data['url'];

            // Laravel's built-in notification would build this from
            // route('password.reset') — a web route this headless API has no
            // reason to define. The page belongs to the storefront.
            $this->assertStringStartsWith(
                'https://goldcoasttokota.store/account/reset-password?token=',
                $url,
            );
            // The broker needs the email alongside the token; the token alone
            // does not identify the account.
            $this->assertStringContainsString('email=ama%40example.com', $url);

            return true;
        });
    }

    public function test_an_unknown_email_gets_the_same_answer_as_a_known_one(): void
    {
        Bus::fake();
        $this->customer();

        $known = $this->postJson('/api/v1/forgot-password', ['email' => 'ama@example.com']);
        $unknown = $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@example.com']);

        // Anything that distinguishes the two turns this endpoint into a way
        // to ask whether a given person shops here.
        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));

        // ...and no email goes anywhere for the unknown address.
        Bus::assertDispatchedTimes(SendPasswordReset::class, 1);
    }

    public function test_an_unknown_email_is_not_a_validation_error(): void
    {
        // `exists:customers,email` would be the obvious rule and would leak
        // the same thing through a 422.
        $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonMissingPath('errors');
    }

    public function test_a_malformed_email_is_still_rejected(): void
    {
        $this->postJson('/api/v1/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_the_reset_email_carries_no_sms_body(): void
    {
        Mail::fake();
        $this->customer();

        $this->postJson('/api/v1/forgot-password', ['email' => 'ama@example.com'])->assertOk();

        Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail) {
            // A reset link is a credential. SMS is forwarded, screenshotted
            // and read off lock screens.
            $this->assertNull($mail->message->sms);

            return true;
        });
    }

    public function test_the_reset_email_renders(): void
    {
        // Mail::fake() never renders the view, so nothing above would catch a
        // broken template.
        $customer = $this->customer();
        $html = (new TransactionalMail(
            TransactionalMessages::passwordReset('https://example.test/reset?token=abc', 60),
            new NotificationRecipient($customer->email, null, $customer->name),
        ))->render();

        $this->assertStringContainsString('https://example.test/reset?token=abc', $html);
        $this->assertStringContainsString('60 minutes', $html);
    }

    // -------------------------------------------------------------- resetting

    public function test_a_valid_token_resets_the_password(): void
    {
        Event::fake([PasswordReset::class]);
        $customer = $this->customer();
        $token = Password::broker('customers')->createToken($customer);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'ama@example.com',
            'password' => 'a-new-password-456',
            'password_confirmation' => 'a-new-password-456',
        ])->assertOk();

        $this->assertTrue(Hash::check('a-new-password-456', $customer->fresh()->password));
        Event::assertDispatched(PasswordReset::class);
    }

    public function test_the_new_password_actually_signs_in_and_the_old_one_stops_working(): void
    {
        $customer = $this->customer();
        $token = Password::broker('customers')->createToken($customer);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'ama@example.com',
            'password' => 'a-new-password-456',
            'password_confirmation' => 'a-new-password-456',
        ])->assertOk();

        $this->postJson('/api/v1/login', [
            'email' => 'ama@example.com',
            'password' => 'old-password-123',
        ])->assertStatus(422);

        $this->postJson('/api/v1/login', [
            'email' => 'ama@example.com',
            'password' => 'a-new-password-456',
        ])->assertOk();
    }

    public function test_a_reset_invalidates_remember_me_cookies_issued_before_it(): void
    {
        $customer = $this->customer(['remember_token' => 'the-old-token']);
        $token = Password::broker('customers')->createToken($customer);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'ama@example.com',
            'password' => 'a-new-password-456',
            'password_confirmation' => 'a-new-password-456',
        ])->assertOk();

        // Somebody resetting a password may be doing it *because* an old
        // session is not theirs.
        $this->assertNotSame('the-old-token', $customer->fresh()->remember_token);
    }

    public function test_a_forged_token_is_refused(): void
    {
        $this->customer();

        $this->postJson('/api/v1/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'ama@example.com',
            'password' => 'a-new-password-456',
            'password_confirmation' => 'a-new-password-456',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password-123', Customer::first()->password));
    }

    public function test_a_token_cannot_be_used_twice(): void
    {
        $customer = $this->customer();
        $token = Password::broker('customers')->createToken($customer);

        $payload = [
            'token' => $token,
            'email' => 'ama@example.com',
            'password' => 'a-new-password-456',
            'password_confirmation' => 'a-new-password-456',
        ];

        $this->postJson('/api/v1/reset-password', $payload)->assertOk();
        // A reset link found later in an inbox must not still work.
        $this->postJson('/api/v1/reset-password', [...$payload, 'password' => 'third-password-789',
            'password_confirmation' => 'third-password-789'])->assertStatus(422);

        $this->assertTrue(Hash::check('a-new-password-456', $customer->fresh()->password));
    }

    public function test_an_expired_token_is_refused(): void
    {
        $customer = $this->customer();
        $token = Password::broker('customers')->createToken($customer);

        // The broker's window is config('auth.passwords.customers.expire'),
        // in minutes.
        $this->travel(config('auth.passwords.customers.expire') + 5)->minutes();

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'ama@example.com',
            'password' => 'a-new-password-456',
            'password_confirmation' => 'a-new-password-456',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password-123', $customer->fresh()->password));
    }

    public function test_a_token_belonging_to_someone_else_is_refused(): void
    {
        $ama = $this->customer();
        $kofi = $this->customer(['email' => 'kofi@example.com']);
        $token = Password::broker('customers')->createToken($ama);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'kofi@example.com',
            'password' => 'a-new-password-456',
            'password_confirmation' => 'a-new-password-456',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password-123', $kofi->fresh()->password));
    }

    public function test_a_weak_password_is_refused(): void
    {
        $customer = $this->customer();
        $token = Password::broker('customers')->createToken($customer);

        // A reset is another way to set a password; a weaker bar here would
        // make registration's stronger one pointless.
        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'ama@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_a_mismatched_confirmation_is_refused(): void
    {
        $customer = $this->customer();
        $token = Password::broker('customers')->createToken($customer);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'ama@example.com',
            'password' => 'a-new-password-456',
            'password_confirmation' => 'a-different-password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }
}
