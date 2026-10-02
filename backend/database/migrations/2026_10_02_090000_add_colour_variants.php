<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Colour becomes a stock axis (FOR_THE_TEAM.md issue 39).
 *
 * Each of the client's photographs is a different colourway of a style, but
 * stock was tracked by size alone — so a customer could not say which colour
 * they were buying, and the shop could not know which pair to send.
 *
 * 1. `products.colour_images` — `{ "Tan": ["/products/domfo/1-tan.webp"] }`,
 *    so the gallery can follow the chosen colour. A column of its own rather
 *    than an `images` key inside `colors`: the listing's colour filter is a
 *    substring search over `colors` as text, and image paths there would make
 *    "tan" match `/products/asantewaa/…`.
 *
 * 2. Existing size-only stock rows are assigned to the product's primary
 *    colour (`products.color`). Nothing is deleted or re-pointed, so counts
 *    the brand has entered stay where they are and every `order_items` row
 *    still references a valid variant. The seeder then adds the missing
 *    colour × size rows (0 in production, for the brand to fill in).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->jsonb('colour_images')->default('{}');
        });

        $primaryColours = DB::table('products')
            ->whereNotNull('color')
            ->where('color', '!=', '')
            ->pluck('color', 'id');

        DB::table('inventory_items')
            ->whereIn('product_id', $primaryColours->keys())
            ->orderBy('id')
            ->each(function (object $row) use ($primaryColours) {
                $attributes = json_decode($row->variant_attributes ?? '{}', true) ?: [];

                if (! empty($attributes['colour'])) {
                    return;
                }

                $attributes['colour'] = $primaryColours[$row->product_id];

                DB::table('inventory_items')
                    ->where('id', $row->id)
                    ->update(['variant_attributes' => json_encode($attributes)]);
            });
    }

    public function down(): void
    {
        // Colour rows the seeder added beyond the primary colour are left in
        // place: they may hold stock or be referenced by orders, and dropping
        // them is a decision, not a rollback.
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('colour_images');
        });
    }
};
