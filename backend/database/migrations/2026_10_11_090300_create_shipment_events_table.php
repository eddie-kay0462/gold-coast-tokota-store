<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only. The model refuses updates and deletes; there is no
        // updated_at because a row is never changed.
        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->nullable();
            $table->string('carrier_status', 64)->nullable();
            $table->string('source', 16);                // poll|webhook|admin|system
            $table->string('message')->nullable();
            $table->jsonb('payload')->nullable();        // the raw carrier response
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
    }
};
