<?php

namespace App\Support;

use App\Services\Delivery\DeliveryEstimates;
use App\Services\Returns\ReturnPolicy;

/**
 * The customer-facing promises in GOLD_COAST_TOKOTA.md, as the team quotes
 * them in a chat.
 *
 * A transcription, not a summary: §22 forbids inventing or changing a
 * policy, so every line below is the brand document's own wording (or an
 * existing constant that already transcribes it), with its section. The
 * inbox's Info panel and the quick replies both read from here, so a
 * WhatsApp answer can never quote a different window than the website.
 */
final class PublishedPolicy
{
    /** §13: accepted payment methods, all through Paystack. */
    public const PAYMENT_METHODS = [
        'Visa',
        'Mastercard',
        'Verve',
        'MTN MoMo',
        'Telecel Cash',
        'AirtelTigo Money, where supported',
        'Bank Transfer',
    ];

    /**
     * §9 and §21 returns, exchanges and refunds.
     *
     * @return list<array{label: string, text: string, section: string}>
     */
    public static function returns(): array
    {
        return [
            ['label' => 'Return window', 'text' => ReturnPolicy::WINDOW_DAYS.' days from receiving the order', 'section' => '§21'],
            ['label' => 'Refund processing', 'text' => 'Approved refunds: '.implode('–', ReturnPolicy::REFUND_BUSINESS_DAYS).' business days', 'section' => '§21'],
            ['label' => 'Size exchange', 'text' => 'Available subject to stock. Customer pays return shipping unless the error was made by Gold Coast Tokota.', 'section' => '§21'],
            ['label' => 'Custom products', 'text' => 'Custom-made sandals and personalized products are non-returnable.', 'section' => '§21'],
            ['label' => 'Sale products', 'text' => 'Clearance/sale items are non-returnable unless defective.', 'section' => '§21'],
            ['label' => 'Handmade variation', 'text' => 'Colour, texture and finish variations are expected and are not defects.', 'section' => '§21'],
            ['label' => 'Unavailable after purchase', 'text' => 'Customer should be offered: replacement, store credit, or full refund.', 'section' => '§21'],
        ];
    }

    /**
     * §8 and §21 delivery times.
     *
     * @return list<array{label: string, text: string, section: string}>
     */
    public static function delivery(): array
    {
        return [
            ['label' => 'Order processing', 'text' => 'Orders are processed within '.DeliveryEstimates::PROCESSING_HOURS.' hours after payment confirmation.', 'section' => '§8'],
            ['label' => 'Ghana', 'text' => 'Standard delivery: '.DeliveryEstimates::DOMESTIC, 'section' => '§21'],
            ...array_map(
                fn (array $band) => ['label' => $band['region'], 'text' => $band['eta'], 'section' => '§21'],
                DeliveryEstimates::bands(),
            ),
        ];
    }
}
