<?php

namespace App\Support;

/**
 * First and last name, to and from one full name.
 *
 * Splitting is only for names that arrived whole (rows from before sign-up
 * asked for two parts, an older storefront still sending `name`): the first
 * word is the first name, the rest the last. It can't know that "Nana Ama"
 * is a first name, so anything that can ask for the two parts should.
 */
final class PersonName
{
    /** @return array{0: string, 1: string|null} */
    public static function split(string $full): array
    {
        $parts = preg_split('/\s+/u', trim($full), 2) ?: [''];

        return [$parts[0], isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null];
    }

    public static function join(?string $first, ?string $last): string
    {
        return trim(trim((string) $first).' '.trim((string) $last));
    }
}
