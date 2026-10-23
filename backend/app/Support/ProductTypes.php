<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Display names for `products.product_type`, shared by the shop filters and the mega menus. */
final class ProductTypes
{
    /** Known types, in the order they're listed when counts tie. */
    public const LABELS = [
        'slippers' => 'Slippers',
        'ahenema' => 'Ahenema',
        'closed-toe' => 'Closed-Toe Shoes',
        'sandals' => 'Sandals',
        'merchandise' => 'Merchandise',
    ];

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? Str::headline($type);
    }

    /** Position for ordering: known types first, in LABELS order, then the rest. */
    public static function rank(string $type): int
    {
        $index = array_search($type, array_keys(self::LABELS), true);

        return $index === false ? PHP_INT_MAX : $index;
    }
}
