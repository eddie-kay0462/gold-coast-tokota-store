<?php

namespace App\Http\Controllers\Dev;

use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

/**
 * Stands in for Paystack's hosted payment page while there is no key.
 *
 * FakeGateway hands the storefront `/fake-gateway/{reference}` as the
 * authorization URL. Visiting it does what a successful charge plus its
 * webhook would — marks the order paid and fires OrderPaid, so stock is
 * finalised and the confirmation email goes out — then returns the customer
 * to the storefront's confirmation page, exactly where Paystack's
 * `callback_url` would. `?outcome=cancel` returns without paying, like closing
 * the Paystack tab.
 *
 * **Registered only outside production** (routes/web.php). In production this
 * URL would be a free order for anyone who found it; there, a keyless API
 * refuses checkout instead (PaymentUnavailableException).
 */
class FakeGatewayController extends Controller
{
    public function __invoke(string $reference): RedirectResponse
    {
        $order = Order::query()->where('payment_reference', $reference)->firstOrFail();

        if ($order->status === 'pending' && request()->query('outcome') !== 'cancel') {
            $order->update(['status' => 'paid']);
            OrderPaid::dispatch($order);
        }

        $storefront = rtrim((string) config('services.paystack.callback_base_url'), '/');

        return redirect()->away("{$storefront}/order-confirmation/{$order->reference}");
    }
}
