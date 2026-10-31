<?php

namespace App\Services\Bookings;

use App\Exceptions\NoFxRateException;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Currency\FxRateService;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentSession;
use App\Support\OrderReference;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Paying for a place at a paid event: an Order of kind `booking` with one line
 * (the tickets) and no delivery, paid through Paystack like any other order.
 *
 * It rides on `Order` for the same reason gift cards do: the Paystack webhook,
 * payment verification, refunds and the confirmation page then work
 * unchanged. The booking is confirmed and its ticket sent when the order is
 * paid (ConfirmBookingForPaidOrder).
 *
 * The booking holds its seats while the customer pays: `awaiting_payment`
 * counts as occupied until `hold_expires_at`, when ReleaseExpiredReservations
 * lets them go (the booking becomes `expired`). The order is left pending, so
 * a payment that still lands is recorded and the booking confirmed.
 */
class BookingCheckout
{
    /** A storefront booking holds its seats as long as a checkout holds stock. */
    public const HOLD_MINUTES = 15;

    /** A waitlist offer: a day to pay before the seat goes to the next person. */
    public const OFFER_HOURS = 24;

    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly FxRateService $fxRates,
    ) {}

    /**
     * Opens payment for a booking that already holds its seats. On failure the
     * order is removed and the exception rethrown; the caller decides what
     * becomes of the booking.
     *
     * @return array{order: Order, session: PaymentSession}
     */
    public function start(Booking $booking, string $currency, CarbonInterface $holdUntil): array
    {
        $gateway = $this->gateways->for($currency);
        $session = $booking->workshopSession()->with('workshopType')->firstOrFail();
        $priceGhs = (int) $session->priceGhs();
        $people = $booking->attendees();

        $order = DB::transaction(function () use ($booking, $currency, $holdUntil, $session, $priceGhs, $people) {
            $fxRate = $currency === 'USD'
                ? ($this->fxRates->getUsableRate()?->rate ?? throw new NoFxRateException)
                : null;
            $unit = $fxRate === null ? $priceGhs : (int) round($priceGhs * (float) $fxRate);
            $details = $booking->details ?? [];

            $order = Order::create([
                'reference' => OrderReference::forOrder(),
                'kind' => 'booking',
                'customer_id' => $booking->customer_id,
                'currency' => $currency,
                'fx_rate_applied' => $fxRate,
                'subtotal' => $unit * $people,
                'shipping_cost' => 0,
                'tax' => 0,
                'total' => $unit * $people,
                'status' => 'pending',
                'reservation_expires_at' => $holdUntil,
                // The booker, where every order keeps its contact details: the
                // receipt and Paystack's email both read it from here.
                'shipping_address' => [
                    'first_name' => $details['first_name'] ?? null,
                    'last_name' => $details['last_name'] ?? null,
                    'full_name' => $details['name'] ?? null,
                    'email' => $details['email'] ?? null,
                    'phone' => $details['phone'] ?? null,
                ],
                'channel' => 'web',
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_name' => $session->eventName().' ticket',
                'variant_label' => trim($session->scheduled_date?->format('D j M Y').' · '.$session->scheduled_slot, ' ·'),
                'quantity' => $people,
                'unit_price' => $unit,
                'currency' => $currency,
            ]);

            $booking->update([
                'order_id' => $order->id,
                'status' => 'awaiting_payment',
                'hold_expires_at' => $holdUntil,
            ]);

            return $order;
        });

        try {
            $payment = $gateway->createSession($order);
        } catch (Throwable $e) {
            $booking->update(['order_id' => null]);
            $order->items()->delete();
            $order->delete();

            throw $e;
        }

        $order->update([
            'payment_gateway' => $payment->gateway,
            'payment_reference' => $payment->reference,
        ]);

        return ['order' => $order->fresh('items'), 'session' => $payment];
    }

    /**
     * Unpaid bookings past their hold give their seats back: they become
     * `expired`. Their orders stay pending, so a payment that still lands
     * confirms the booking (ConfirmBookingForPaidOrder).
     */
    public function releaseLapsed(): int
    {
        return Booking::query()
            ->where('status', 'awaiting_payment')
            ->where('hold_expires_at', '<=', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);
    }
}
