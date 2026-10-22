<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * When the team is at work, for the after-hours reply.
 *
 * §14: "Our team typically responds during business hours: Monday – Saturday:
 * 9:00 AM – 5:00 PM (GMT)". That is the default; the owner can change it on
 * the automation settings. Accra keeps GMT all year, so `Africa/Accra` is the
 * clock, with no daylight saving to get wrong.
 */
final class BusinessHours
{
    public const TIMEZONE = 'Africa/Accra';

    public const DEFAULT = ['days' => [1, 2, 3, 4, 5, 6], 'open' => '09:00', 'close' => '17:00'];

    /** @param  list<int>  $days  ISO weekdays, 1 = Monday */
    public function __construct(
        public readonly array $days,
        public readonly string $open,
        public readonly string $close,
    ) {}

    /** @param  array{days?: list<int>, open?: string, close?: string}|null  $stored */
    public static function from(?array $stored): self
    {
        $hours = [...self::DEFAULT, ...array_filter($stored ?? [], fn ($v) => $v !== null && $v !== [])];

        return new self(array_values(array_map('intval', $hours['days'])), (string) $hours['open'], (string) $hours['close']);
    }

    public function isOpen(CarbonInterface $at): bool
    {
        $local = CarbonImmutable::instance($at)->setTimezone(self::TIMEZONE);

        if (! in_array($local->isoWeekday(), $this->days, true)) {
            return false;
        }

        $time = $local->format('H:i');

        return $time >= $this->open && $time < $this->close;
    }

    /**
     * When the current closed spell began: the most recent closing time at
     * or before `$at`. The after-hours reply is sent once per spell, so a
     * customer writing three times overnight hears it once.
     */
    public function closedSince(CarbonInterface $at): ?CarbonImmutable
    {
        $local = CarbonImmutable::instance($at)->setTimezone(self::TIMEZONE);

        for ($back = 0; $back <= 8; $back++) {
            $day = $local->subDays($back);

            if (! in_array($day->isoWeekday(), $this->days, true)) {
                continue;
            }

            [$h, $m] = array_map('intval', explode(':', $this->close));
            $closing = $day->setTime($h, $m);

            if ($closing->lessThanOrEqualTo($local)) {
                return $closing;
            }
        }

        return null;
    }

    /** "Monday – Saturday, 9:00 AM – 5:00 PM (GMT)", for messages. */
    public function describe(): string
    {
        $names = [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $days = $this->days;
        sort($days);
        $contiguous = $days !== [] && end($days) - $days[0] === count($days) - 1;

        $dayText = match (true) {
            $days === [] => 'no days',
            count($days) === 1 => $names[$days[0]],
            $contiguous => $names[$days[0]].' – '.$names[end($days)],
            default => implode(', ', array_map(fn ($d) => $names[$d], $days)),
        };

        $fmt = fn (string $t) => CarbonImmutable::createFromFormat('H:i', $t, self::TIMEZONE)->format('g:i A');

        return "{$dayText}, {$fmt($this->open)} – {$fmt($this->close)} (GMT)";
    }

    /** @return array{days: list<int>, open: string, close: string} */
    public function toArray(): array
    {
        return ['days' => $this->days, 'open' => $this->open, 'close' => $this->close];
    }
}
