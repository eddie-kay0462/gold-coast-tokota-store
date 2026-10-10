<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a late payment tell whether its stock hold is still on the shelf.
 *
 * Holds are one counter per stock row, not one row per order, and an expired
 * counter is wiped whole. A customer who pays after their 15 minutes used to
 * be finalised as if the hold were still there: the decrement came out of
 * whoever had reserved since, and a unit already resold was sold twice.
 *
 * 1. `orders.reservation_expires_at` — when this order's own hold lapses.
 * 2. `inventory_items.reservations_cleared_at` — when the row's counter was
 *    last wiped by expiry. A wipe at or after an order's own expiry took that
 *    order's hold with it; one before it happened before the order reserved.
 *
 * Both nullable: orders placed before this migration finalise as they always
 * did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('reservation_expires_at')->nullable();
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->timestamp('reservations_cleared_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reservation_expires_at');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('reservations_cleared_at');
        });
    }
};
