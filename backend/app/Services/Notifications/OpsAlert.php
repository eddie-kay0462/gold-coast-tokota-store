<?php

namespace App\Services\Notifications;

use App\Jobs\SendOpsAlert;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Log;

/**
 * Staff alerts for shipments that need a person: a new order to dispatch by
 * hand, a courier that never came, a booking that failed.
 *
 * Email and SMS go to OPS_ALERT_EMAIL and OPS_ALERT_PHONE through the same
 * channels customers' messages use. The admin "Needs attention" list is the
 * third channel, and needs nothing from here — it reads shipment status. Like
 * NotificationDispatcher this never throws: an alert is not a reason to fail
 * the work that raised it.
 */
class OpsAlert
{
    public function newManualShipment(Shipment $shipment): void
    {
        $order = $shipment->order;
        $address = $order->shipping_address ?? [];
        $where = trim(($address['city'] ?? '').', '.($address['region'] ?? ''), ', ');

        $this->send(
            key: 'ops_manual_shipment',
            subject: "Dispatch by hand: order {$order->reference}",
            body: "Order {$order->reference} has been paid and needs a courier booked by hand"
                .($where !== '' ? " for delivery to {$where}" : '').'. '
                .'Record the courier and tracking number on the order once it is booked.',
            sms: "GCT ops: order {$order->reference} paid, book a courier by hand"
                .($where !== '' ? " ({$where})" : '').'.',
            order: $order,
        );
    }

    public function shipmentFailed(Shipment $shipment, string $reason): void
    {
        $order = $shipment->order;

        $this->send(
            key: 'ops_shipment_failed',
            subject: "Delivery needs attention: order {$order->reference}",
            body: "The {$shipment->carrier} delivery for order {$order->reference} has failed: {$reason}. "
                .'Arrange delivery by hand from the order screen.',
            sms: "GCT ops: delivery failed for {$order->reference} ({$shipment->carrier}). Check admin.",
            order: $order,
        );
    }

    /**
     * Money arrived that the order cannot keep: a second payment through a
     * resent link, or a payment for an order the team had cancelled. Nothing
     * automatic can undo a charge, so a person refunds it in Paystack.
     */
    public function paymentNeedsRefund(Order $order, string $paymentReference, string $why): void
    {
        $this->send(
            key: 'ops_payment_refund',
            subject: "Refund needed: order {$order->reference}",
            body: "Paystack payment {$paymentReference} for order {$order->reference} cannot be kept: {$why}. "
                .'Refund it from the Paystack dashboard, or contact the customer if they meant to reinstate the order.',
            sms: "GCT ops: refund needed for {$order->reference} (payment {$paymentReference}). Check admin.",
            order: $order,
        );
    }

    private function send(string $key, string $subject, string $body, ?string $sms, Order $order): void
    {
        if (! config('shipping.ops.email') && ! config('shipping.ops.phone')) {
            Log::warning('Ops alert has nowhere to go; set OPS_ALERT_EMAIL or OPS_ALERT_PHONE.', [
                'alert' => $key,
                'order_reference' => $order->reference,
            ]);

            return;
        }

        SendOpsAlert::dispatch($key, $subject, $body, $sms, config('app.admin_url').'/orders/'.$order->reference);
    }
}
