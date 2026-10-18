<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flat rates for the GHANA zone, one per administrative region.
        Schema::create('shipping_zone_rates', function (Blueprint $table) {
            $table->id();
            $table->string('region', 64)->unique();
            // GHS minor units.
            $table->unsignedBigInteger('amount_ghs')->default(0);
            // Seeded inactive: an unpriced region is not offered at checkout
            // until someone has actually set its rate.
            $table->boolean('is_active')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_rates');
    }
};
