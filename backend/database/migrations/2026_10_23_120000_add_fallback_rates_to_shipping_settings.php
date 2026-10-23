<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The fallback delivery rates (charged when a courier can't quote, and for a
 * Ghana region with no rate of its own) move from env into the admin, beside
 * the free-shipping threshold. GHS minor units; null falls back to
 * config('shipping.fallback_rates').
 *
 * Accra and Ghana start at ₵35, the client's figure (was ₵25). Written to the
 * row rather than left to the config default, because an env var still set to
 * 2500 would otherwise go on charging ₵25. International stays on its config
 * value (₵350) until someone sets it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            $table->unsignedInteger('fallback_accra_ghs')->nullable()->after('free_shipping_threshold_ghs');
            $table->unsignedInteger('fallback_ghana_ghs')->nullable()->after('fallback_accra_ghs');
            $table->unsignedInteger('fallback_international_ghs')->nullable()->after('fallback_ghana_ghs');
        });

        $values = ['fallback_accra_ghs' => 3_500, 'fallback_ghana_ghs' => 3_500, 'updated_at' => now()];
        if (DB::table('shipping_settings')->exists()) {
            DB::table('shipping_settings')->update($values);
        } else {
            DB::table('shipping_settings')->insert($values + ['created_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            $table->dropColumn(['fallback_accra_ghs', 'fallback_ghana_ghs', 'fallback_international_ghs']);
        });
    }
};
