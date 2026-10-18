<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per carrier booking attempt. A retry, or a hand-over from
        // Yango to Swoove, is a new row that supersedes the old one, so every
        // row has exactly one carrier and one carrier reference.
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            // Sent to carriers as the idempotency key, so a retried request
            // can never book a second courier.
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('zone', 16)->index();             // accra|ghana|international
            $table->string('carrier', 16)->index();          // yango|swoove|dhl|manual
            $table->string('service_code', 32)->nullable();  // DHL productCode, Yango taxi_class
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('shipments')->nullOnDelete();

            $table->string('carrier_reference', 128)->nullable()->index();
            $table->unsignedInteger('carrier_version')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->string('carrier_status', 64)->nullable();

            // Minor units, each with its own currency: a carrier may quote
            // and bill in a currency other than the order's.
            $table->unsignedBigInteger('quoted_cost')->nullable();
            $table->char('quoted_currency', 3)->nullable();
            $table->unsignedBigInteger('actual_cost')->nullable();
            $table->char('actual_currency', 3)->nullable();

            $table->text('tracking_url')->nullable();
            $table->string('label_path')->nullable();     // r2-private
            $table->string('invoice_path')->nullable();   // r2-private
            $table->string('pickup_reference')->nullable();
            $table->string('courier_name')->nullable();
            $table->text('notes')->nullable();
            $table->string('failure_reason')->nullable();

            $table->foreignId('packed_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamp('next_poll_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
