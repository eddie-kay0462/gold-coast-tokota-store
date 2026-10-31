<?php

namespace App\Services\GiftCards;

use App\Exceptions\NoFxRateException;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Currency\FxRateService;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentSession;
use App\Support\OrderReference;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Buying a gift card online: an Order of kind `gift_card` with one line and no
 * delivery, paid through Paystack like any other order.
 *
 * Kept apart from the sandal checkout on purpose. A gift card holds no stock
 * and ships nowhere, so none of that path's delivery pricing or reservations
 * apply, and it can't be paid for with another gift card. Riding on `Order`
 * is what lets the Paystack webhook, payment verification and the order
 * confirmation page work unchanged; the card itself is issued when the order
 * is paid (IssueGiftCardForPaidOrder).
 */
class GiftCardCheckout
{
    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly FxRateService $fxRates,
    ) {}

    /**
     * @param  array{amount: int, currency: string, purchaser: array{first_name: string, last_name?: string|null, full_name: string, email: string, phone?: string|null}, recipient_name: string, recipient_email: string, sender_name?: string|null, message?: string|null, customer_id?: int|null}  $data
     * @return array{order: Order, session: PaymentSession}
     */
    public function start(array $data): array
    {
        $gateway = $this->gateways->for($data['currency']);

        $order = DB::transaction(function () use ($data) {
            $amountGhs = $data['amount'];
            $fxRate = $data['currency'] === 'USD'
                ? ($this->fxRates->getUsableRate()?->rate ?? throw new NoFxRateException)
                : null;
            $charged = $fxRate === null ? $amountGhs : (int) round($amountGhs * (float) $fxRate);

            $order = Order::create([
                'reference' => OrderReference::forOrder(),
                'kind' => 'gift_card',
                'customer_id' => $data['customer_id'] ?? null,
                'currency' => $data['currency'],
                'fx_rate_applied' => $fxRate,
                'subtotal' => $charged,
                'shipping_cost' => 0,
                'tax' => 0,
                'total' => $charged,
                'status' => 'pending',
                // The purchaser, where every order keeps its contact details:
                // the receipt and Paystack's email both read it from here.
                'shipping_address' => $data['purchaser'],
                'channel' => 'web',
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_name' => 'Gift card',
                'variant_label' => "For {$data['recipient_name']}",
                'quantity' => 1,
                'unit_price' => $charged,
                'currency' => $data['currency'],
                'gift_card' => [
                    'amount_ghs' => $amountGhs,
                    'recipient_name' => $data['recipient_name'],
                    'recipient_email' => $data['recipient_email'],
                    'sender_name' => $data['sender_name'] ?? null,
                    'message' => $data['message'] ?? null,
                    'code_word' => GiftCard::codeWord($data['code_word'] ?? null),
                ],
            ]);

            return $order;
        });

        try {
            $session = $gateway->createSession($order);
        } catch (Throwable $e) {
            $order->delete();

            throw $e;
        }

        $order->update([
            'payment_gateway' => $session->gateway,
            'payment_reference' => $session->reference,
        ]);

        return ['order' => $order->fresh('items'), 'session' => $session];
    }
}
