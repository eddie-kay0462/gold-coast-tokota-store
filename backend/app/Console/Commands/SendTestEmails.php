<?php

namespace App\Console\Commands;

use App\Mail\TransactionalMail;
use App\Models\Booking;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WorkshopSession;
use App\Models\WorkshopType;
use App\Notifications\NotificationMessage;
use App\Notifications\NotificationRecipient;
use App\Notifications\TransactionalMessages;
use App\Services\GiftCards\GiftCardService;
use App\Support\OrderReference;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One of every customer and staff email, sent to one address, to check the
 * mail provider and how each email looks in a real inbox: `php artisan
 * mail:test you@example.com`.
 *
 * The orders, gift card and booking they describe are made up and never kept:
 * they're created inside a transaction that's rolled back once the emails are
 * sent, so the ticket PDF is rendered exactly as a customer's would be. Mail
 * goes out directly through the configured mailer (`MAIL_MAILER`), not the
 * queue, so a provider error shows here. Works on the production box: no test
 * factories are needed.
 */
class SendTestEmails extends Command
{
    protected $signature = 'mail:test
        {to : The address to send them to}
        {--only= : Comma-separated keys to send just those, e.g. booking_confirmed,admin_invite}';

    protected $description = 'Send one of every transactional email to an address, using made-up data that is not kept';

    public function handle(GiftCardService $giftCards): int
    {
        $to = (string) $this->argument('to');
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('That isn’t an email address.');

            return self::FAILURE;
        }
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        $this->info('Mailer: '.config('mail.default').' · from '.config('mail.from.address'));
        if (config('mail.default') === 'log') {
            $this->warn('MAIL_MAILER is "log": these are written to the log, not sent. Set MAIL_MAILER=resend and RESEND_API_KEY to send.');
        }

        $sent = 0;
        $failed = 0;

        DB::beginTransaction();
        try {
            foreach ($this->messages($giftCards) as $key => $message) {
                if ($only && ! in_array($key, $only, true)) {
                    continue;
                }
                try {
                    Mail::to($to, 'Ama Serwaa')->send(new TransactionalMail($message, new NotificationRecipient($to, null, 'Ama Serwaa')));
                    $this->line("  <info>sent</info>   {$key}  ·  {$message->subject}");
                    $sent++;
                } catch (Throwable $e) {
                    $this->line("  <error>failed</error> {$key}  ·  ".$e->getMessage());
                    $failed++;
                }
            }
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        $this->info("{$sent} sent, {$failed} failed. Nothing was saved.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return iterable<string, NotificationMessage> */
    private function messages(GiftCardService $giftCards): iterable
    {
        $address = [
            'first_name' => 'Ama', 'last_name' => 'Serwaa', 'full_name' => 'Ama Serwaa',
            'email' => 'ama@example.com', 'phone' => '+233241234567',
            'line1' => '12 Haatso Road', 'city' => 'Accra', 'region' => 'Greater Accra', 'country' => 'GH',
        ];

        // A paid sandal order.
        $order = Order::create([
            'reference' => OrderReference::forOrder(), 'kind' => 'goods', 'currency' => 'GHS',
            'subtotal' => 60_000, 'shipping_cost' => 3_500, 'tax' => 0, 'total' => 63_500,
            'status' => 'paid', 'shipping_address' => $address, 'channel' => 'web',
            'delivery_provider' => 'manual', 'delivery_reference' => 'TEST-1234',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_name' => 'Obaapa Collection', 'variant_label' => '40 · Tan', 'quantity' => 2, 'unit_price' => 30_000, 'currency' => 'GHS']);
        $order->load('items');
        yield 'order_placed' => TransactionalMessages::orderConfirmation($order);
        yield 'order_shipped' => TransactionalMessages::orderShipped($order->fresh(['items', 'shipment']));

        // A gift card bought online: the recipient's email and the buyer's receipt.
        $card = $giftCards->issue(50_000, GiftCard::SOURCE_PURCHASE, [
            'recipient_name' => 'Kofi Mensah', 'recipient_email' => 'kofi@example.com',
            'sender_name' => 'Ama', 'message' => 'Happy birthday, Kofi! Pick a pair you love.', 'code_word' => 'kofi',
        ]);
        yield 'gift_card_issued' => TransactionalMessages::giftCard($card);
        $giftOrder = Order::create([
            'reference' => OrderReference::forOrder(), 'kind' => 'gift_card', 'currency' => 'GHS',
            'subtotal' => 50_000, 'shipping_cost' => 0, 'tax' => 0, 'total' => 50_000,
            'status' => 'paid', 'shipping_address' => $address, 'channel' => 'web',
        ]);
        OrderItem::create(['order_id' => $giftOrder->id, 'product_name' => 'Gift card', 'variant_label' => 'For Kofi Mensah', 'quantity' => 1, 'unit_price' => 50_000, 'currency' => 'GHS',
            'gift_card' => ['amount_ghs' => 50_000, 'recipient_name' => 'Kofi Mensah', 'recipient_email' => 'kofi@example.com', 'gift_card_id' => $card->id]]);
        yield 'gift_card_purchased' => TransactionalMessages::giftCardReceipt($giftOrder->load('items'));

        // A paid event: booked, confirmed with its ticket, and a waitlist offer.
        $type = WorkshopType::create([
            'name' => 'Sandal Sip & Paint', 'slug' => 'test-email-sip-and-paint-'.uniqid(),
            'days_label' => 'Every Saturday', 'slot_label' => '10:00 AM to 1:00 PM', 'duration_label' => '3 hours',
            'capacity' => 20, 'price_ghs' => 15_000, 'allow_waitlist' => true, 'is_active' => false, 'sort_order' => 999,
        ]);
        $session = WorkshopSession::create([
            'workshop_type_id' => $type->id, 'title' => 'Halloween Sip & Paint',
            'scheduled_date' => now()->addWeek()->toDateString(), 'scheduled_slot' => '10:00 AM to 1:00 PM',
            'capacity' => 20, 'location_notes' => 'Gold Coast Tokota workshop, Haatso, Accra',
        ]);
        $details = ['first_name' => 'Ama', 'last_name' => 'Serwaa', 'name' => 'Ama Serwaa', 'email' => 'ama@example.com', 'phone' => '+233241234567', 'attendee_count' => 2];
        $booking = Booking::create(['type' => 'workshop', 'workshop_session_id' => $session->id, 'status' => 'pending', 'details' => $details]);
        $booking->load('workshopSession.workshopType');
        yield 'booking_submitted' => TransactionalMessages::bookingSubmitted($booking);
        $booking->update(['status' => 'confirmed']);
        yield 'booking_confirmed' => TransactionalMessages::bookingConfirmed($booking->fresh(['workshopSession.workshopType', 'order']));
        $offered = Booking::create(['type' => 'workshop', 'workshop_session_id' => $session->id, 'status' => 'awaiting_payment', 'hold_expires_at' => now()->addDay(), 'details' => $details]);
        yield 'waitlist_promoted' => TransactionalMessages::waitlistPromoted($offered->load('workshopSession.workshopType'), 'https://checkout.paystack.com/test-link');

        // Accounts: a team invitation and a password reset.
        $admin = rtrim((string) config('app.admin_url'), '/');
        yield 'admin_invite' => TransactionalMessages::adminInvite($admin.'/reset-password?token=test&invite=1', 'Samuel Kumi-Gyau', 'Staff', intdiv((int) config('auth.passwords.admin_invites.expire'), 60 * 24));
        yield 'password_reset' => TransactionalMessages::passwordReset($admin.'/reset-password?token=test', 60);
    }
}
