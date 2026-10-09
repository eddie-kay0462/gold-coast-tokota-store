<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two facts §9's returns policy needs and neither table carried.
 *
 * `orders.delivered_at` — the window is "7 days of **receiving** the order",
 * not seven days from placing or shipping it. `status = 'delivered'` says the
 * order arrived but not when, so the deadline was uncomputable.
 *
 * `products.is_returnable` — §9 makes custom-made sandals and personalised
 * products non-returnable outright. Nothing on `products` said which those
 * were: `product_type` is the listing facet (ahenema, slippers…) and `tags` is
 * free-text merchandising copy, so keying a refund decision off either would
 * mean a badge rename could quietly change what customers are owed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('status');
        });

        Schema::table('products', function (Blueprint $table) {
            // Default true: an ordinary pair off the shelf is returnable, and
            // §9's exclusions are the exception rather than the rule. Sale
            // items are NOT flagged here — "clearance or sale items, unless
            // defective" is a conditional exclusion, so it is decided per
            // request against compare_at_ghs, not stored as a flag.
            $table->boolean('is_returnable')->default(true)->after('is_pre_order');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_returnable');
        });
    }
};
