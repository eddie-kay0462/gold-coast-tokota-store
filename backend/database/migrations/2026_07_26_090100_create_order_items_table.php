<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Nullable + nullOnDelete (not cascade): an order line is a
            // historical record and must survive a product being hard-deleted
            // later, unlike InventoryItem which is meaningless without one.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            // Snapshotted at order time (minor units) — never re-derived from
            // the product's current price, so a later price change can't
            // alter a historical order's total.
            $table->unsignedBigInteger('unit_price');
            $table->string('currency');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
