<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock holds become one row per order, and every stock change is logged.
 *
 * Until now a variant's holds were one counter (`quantity_reserved`) with one
 * clock (`reservation_expires_at`) that only ever extended. That worked while
 * every hold was a 15-minute checkout. A WhatsApp order waits on payment for
 * hours, and on a shared clock it would keep every abandoned web checkout on
 * the same size held for just as long. So:
 *
 * 1. `stock_holds`: one row per order per variant, with its own expiry. The
 *    `quantity_reserved` column stays, as the sum of the active holds, kept
 *    under the same row lock, so every reader of `sellable_quantity` is
 *    unchanged. `reservation_expires_at` stays as the latest active expiry,
 *    for display only.
 * 2. `inventory_movements`: an append-only ledger of every change to a
 *    variant's counts: holds, releases, expiries, sales and admin adjustments.
 * 3. `orders.channel`: where an order came from (`web` or `whatsapp`), plus
 *    the admin who built it and the conversation it belongs to.
 *
 * `inventory_items.reservations_cleared_at` is dropped. It existed only so a
 * late payment could tell whether a wipe of the shared counter had taken its
 * hold; a hold row now says that itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('channel')->default('web')->index();
            // The conversations table arrives with the WhatsApp inbox; until
            // then this is a plain column, and the foreign key is added there.
            $table->unsignedBigInteger('whatsapp_conversation_id')->nullable()->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
        });

        Schema::create('stock_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('channel')->default('web');
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            // `released` (checkout abandoned, order cancelled) or `expired`.
            $table->string('release_reason')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['inventory_item_id', 'released_at', 'finalized_at']);
            $table->index(['order_id']);
            $table->index(['expires_at']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->integer('delta_available')->default(0);
            $table->integer('delta_reserved')->default(0);
            // Counts after the change, so the history reads without arithmetic.
            $table->integer('quantity_available_after');
            $table->integer('quantity_reserved_after');
            // hold | hold_released | hold_expired | sale | adjustment
            $table->string('reason');
            $table->string('channel')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventory_item_id', 'created_at']);
        });

        // Checkouts in flight at deploy time keep their holds. Each pending
        // order with an unexpired hold gets a row per line; anything else in
        // the old counter belonged to a hold that has already run out.
        $now = now();

        $lines = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'pending')
            ->whereNotNull('orders.reservation_expires_at')
            ->where('orders.reservation_expires_at', '>', $now)
            ->whereNotNull('order_items.inventory_item_id')
            ->get(['orders.id as order_id', 'orders.reservation_expires_at', 'order_items.inventory_item_id', 'order_items.quantity']);

        foreach ($lines as $line) {
            DB::table('stock_holds')->insert([
                'inventory_item_id' => $line->inventory_item_id,
                'order_id' => $line->order_id,
                'quantity' => $line->quantity,
                'channel' => 'web',
                'expires_at' => $line->reservation_expires_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('inventory_items')->update(['quantity_reserved' => 0, 'reservation_expires_at' => null]);

        $totals = DB::table('stock_holds')
            ->groupBy('inventory_item_id')
            ->get(['inventory_item_id', DB::raw('SUM(quantity) as reserved'), DB::raw('MAX(expires_at) as expires_at')]);

        foreach ($totals as $total) {
            DB::table('inventory_items')->where('id', $total->inventory_item_id)->update([
                'quantity_reserved' => (int) $total->reserved,
                'reservation_expires_at' => $total->expires_at,
            ]);
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('reservations_cleared_at');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->timestamp('reservations_cleared_at')->nullable();
        });

        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('stock_holds');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_admin_id');
            $table->dropIndex(['whatsapp_conversation_id']);
            $table->dropColumn('whatsapp_conversation_id');
            $table->dropIndex(['channel']);
            $table->dropColumn('channel');
        });
    }
};
