<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook idempotency (README Feature 4).
 *
 * Paystack retries a webhook it did not get a 200 for, and a retry that runs
 * the handler a second time would finalise the same reservation twice —
 * decrementing stock for one sale, twice. The unique index is the guard: the
 * insert either succeeds, in which case this delivery is the first, or it
 * violates the constraint and the delivery is a replay to be acknowledged and
 * dropped.
 *
 * Keyed on (gateway, event_id) rather than event_id alone: two providers can
 * legitimately mint the same id, and the pair costs nothing to carry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            // Paystack has no per-delivery event id, so this is the event
            // name plus the transaction reference — unique per state change,
            // which is the property idempotency actually needs.
            $table->string('event_id');
            $table->string('event_type')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_webhook_events');
    }
};
