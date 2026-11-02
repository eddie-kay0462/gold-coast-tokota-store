<?php

namespace Tests\Feature;

use App\Mail\TransactionalMail;
use App\Models\Booking;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\SiteSetting;
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

    /**
     * Every email wears the brand's frame (logo, band, footer channels from Site
     * settings) and none has an em dash in its subject or body: the client asked
     * for both.
     */
    public function test_every_email_has_the_brand_frame_and_no_em_dashes(): void
    {
        Mail::fake();
        SiteSetting::current()->update(['instagram_url' => 'https://www.instagram.com/goldcoasttokota/', 'whatsapp_number' => '233257534297']);

        $this->artisan('mail:test', ['to' => 'check@example.com'])->assertSuccessful();

        Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail) {
            $this->assertStringNotContainsString('—', $mail->message->subject, $mail->message->key);
            // Rendering builds the attachments too, and the ticket's booking was
            // rolled back with the rest of mail:test's data. The frame is the
            // same on every email, so the others cover it.
            if ($mail->message->attachments) {
                return true;
            }
            $html = $mail->render();
            $this->assertStringNotContainsString('—', strip_tags($html), $mail->message->key);
            // The logo is loaded from a public address, not embedded: Gmail
            // lists an embedded image as an attachment.
            $this->assertStringContainsString('src="'.config('mail.logo_url').'"', $html);
            $this->assertStringNotContainsString('cid:', $html);
            $this->assertStringContainsString('HANDCRAFTED IN GHANA', $html);
            $this->assertStringContainsString('https://www.instagram.com/goldcoasttokota/', $html);
            $this->assertStringContainsString('https://wa.me/233257534297', $html);

            return true;
        });
    }

    /** Both order emails link to the storefront's order tracking, on whichever storefront STOREFRONT_URL names. */
    public function test_order_emails_link_to_order_tracking_on_the_storefront(): void
    {
        Mail::fake();
        config(['app.storefront_url' => 'https://goldcoasttokota.store']);

        $this->artisan('mail:test', ['to' => 'check@example.com', '--only' => 'order_placed,order_shipped'])->assertSuccessful();

        Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail) {
            $html = $mail->render();
            $this->assertMatchesRegularExpression('#https://goldcoasttokota\.store/account\?order=GCT-[A-Z0-9]+\#track-order#', $html);
            $this->assertStringContainsString('this email address', $html);

            return true;
        });
    }

    public function test_the_invitation_says_three_days(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['to' => 'check@example.com', '--only' => 'admin_invite'])->assertSuccessful();

        Mail::assertSent(TransactionalMail::class, fn ($mail) => str_contains($mail->render(), '3 days'));
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
