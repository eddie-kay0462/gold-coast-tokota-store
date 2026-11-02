<?php

namespace Tests\Feature;

use App\Mail\TransactionalMail;
use App\Models\Booking;
use App\Models\GiftCard;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** `mail:test` sends one of every email and keeps none of the data it made up. */
class SendTestEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_every_email_and_keeps_nothing(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['to' => 'check@example.com'])
            ->expectsOutputToContain('9 sent, 0 failed')
            ->assertSuccessful();

        Mail::assertSent(TransactionalMail::class, 9);
        Mail::assertSent(TransactionalMail::class, fn ($m) => $m->hasTo('check@example.com') && $m->message->key === 'booking_confirmed');
        $this->assertSame(0, Order::count());
        $this->assertSame(0, GiftCard::count());
        $this->assertSame(0, Booking::count());
    }

    public function test_only_sends_the_ones_asked_for(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['to' => 'check@example.com', '--only' => 'admin_invite,password_reset'])->assertSuccessful();

        Mail::assertSent(TransactionalMail::class, 2);
    }

    public function test_it_refuses_something_that_isnt_an_address(): void
    {
        $this->artisan('mail:test', ['to' => 'not-an-address'])->assertFailed();
    }
}
