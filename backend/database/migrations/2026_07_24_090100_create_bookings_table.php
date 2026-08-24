<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            // 'workshop' | 'diy_order' — validated at the Form Request layer
            // (see StoreBookingRequest), not a DB enum, so a third booking
            // type never needs a migration.
            $table->string('type');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // Only set for type=workshop.
            $table->foreignId('workshop_session_id')->nullable()->constrained()->nullOnDelete();
            // Only meaningful for type=diy_order per the README data model;
            // the current DIY form (no date/capacity constraint) doesn't
            // populate it, kept for a future admin-assigned pickup date.
            $table->date('scheduled_date')->nullable();
            // Type-specific payload AND guest contact info (name/email/phone)
            // — Booking has no dedicated guest-contact columns, same pattern
            // Order uses (contact folded into shipping_address jsonb) since
            // customer_id is nullable for guest submissions either way.
            $table->jsonb('details')->default('{}');
            // 'pending' | 'confirmed' | 'waitlisted' | 'completed' | 'cancelled'
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('scheduled_date');
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
