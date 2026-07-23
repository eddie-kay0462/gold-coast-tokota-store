<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // e.g. {"size": "42", "color": "brown"} — one row per sellable variant.
            $table->jsonb('variant_attributes')->default('{}');
            $table->unsignedInteger('quantity_available')->default(0);
            // Soft-reservation columns (Feature 3) — unused until checkout reservation
            // logic lands, present now so Product's in-stock state has a home.
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->timestamp('reservation_expires_at')->nullable();
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
