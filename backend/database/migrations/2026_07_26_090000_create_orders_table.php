<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // 'GHS' | 'USD' — routes payment_gateway/delivery_provider deterministically
            // (Feature 4/5), validated at the Form Request layer, not a DB enum.
            $table->string('currency');
            // Snapshotted at checkout-session creation for USD orders so the
            // charged amount is immune to later FX rate fluctuations (Feature 2).
            $table->decimal('fx_rate_applied', 12, 6)->nullable();
            // All money in minor units (pesewas/cents), matching products.base_price_ghs.
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_cost');
            $table->unsignedBigInteger('tax');
            $table->unsignedBigInteger('total');
            // pending|paid|processing|shipped|delivered|cancelled|refunded|inventory_conflict
            $table->string('status')->default('pending');
            // 'paystack' | 'stripe' — nullable until Feature 4 wires the gateways.
            $table->string('payment_gateway')->nullable();
            $table->string('payment_reference')->nullable();
            // 'yango' | 'dhl' — nullable until Feature 5 wires delivery routing.
            $table->string('delivery_provider')->nullable();
            $table->string('delivery_reference')->nullable();
            // Guest contact + address — same pattern as Booking.details: no
            // dedicated guest-contact columns since customer_id is nullable.
            $table->jsonb('shipping_address')->default('{}');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
