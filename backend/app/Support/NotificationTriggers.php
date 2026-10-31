<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * The customer messages an admin can switch off per channel, in Settings ›
 * Notifications. The five README Feature 8 and the dispatch promise name.
 *
 * Anything else (password resets, gift cards) always goes: switching off the
 * email that carries someone's gift card code would lose them the card.
 * WhatsApp updates aren't listed either; they only reach customers who wrote
 * in first, and the inbox's own settings govern them.
 */
final class NotificationTriggers
{
    /** Channel names as the notification channels report them. */
    public const CHANNELS = ['email' => 'mail', 'sms' => 'sms'];

    public const TRIGGERS = [
        'order_placed' => 'Order placed',
        'order_shipped' => 'Order dispatched',
        'booking_submitted' => 'Booking submitted',
        'booking_confirmed' => 'Booking confirmed',
        'waitlist_promoted' => 'Waitlist promotion',
    ];

    /** Whether this message goes out on this channel (`mail`, `sms`, `whatsapp`). */
    public static function allows(string $key, string $channel): bool
    {
        $setting = array_search($channel, self::CHANNELS, true);

        if (! isset(self::TRIGGERS[$key]) || $setting === false) {
            return true;
        }

        return (bool) (SiteSetting::current()->notification_channels[$key][$setting] ?? true);
    }

    /** @return list<array{key: string, label: string, email: bool, sms: bool}> */
    public static function all(): array
    {
        $saved = SiteSetting::current()->notification_channels ?? [];

        return array_map(fn (string $key, string $label) => [
            'key' => $key,
            'label' => $label,
            'email' => (bool) ($saved[$key]['email'] ?? true),
            'sms' => (bool) ($saved[$key]['sms'] ?? true),
        ], array_keys(self::TRIGGERS), self::TRIGGERS);
    }
}
