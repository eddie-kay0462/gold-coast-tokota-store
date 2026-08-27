<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The order number shown on the confirmation page, and the only key
            // `GET /orders/{reference}` accepts.
            //
            // Deliberately NOT the numeric id. Guest checkout means that
            // endpoint cannot be behind auth, and an order carries a name, an
            // email and a home address — so a sequential id would let anyone
            // walk the whole order table by counting. This is random enough
            // that guessing one is not a practical attack, and it doubles as
            // something a customer can quote over WhatsApp.
            $table->string('reference')->unique()->after('id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Snapshotted at order time for the same reason unit_price is:
            // product_id is nullOnDelete, so a hard-deleted product must not
            // take the name off a historical receipt. The confirmation page
            // reads these, never the live product.
            $table->string('product_name')->after('product_id');
            $table->string('variant_label')->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reference');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'variant_label']);
        });
    }
};
