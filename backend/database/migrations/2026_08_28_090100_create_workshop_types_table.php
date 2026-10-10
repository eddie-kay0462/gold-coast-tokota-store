<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The six experiences §15 of the brand document defines, and the link from a
 * scheduled session back to the one it runs.
 *
 * Until now a `workshop_session` was a date, a time and a number of seats with
 * **no name** — so the storefront's booking page could not tell a Saturday Sip
 * & Paint from a Friday Be a Shoemaker for a Day, and the admin Workshops
 * screen had nothing to group sessions under. The programme was the missing
 * half of Feature 7, and §15 is where it was written down: each experience has
 * its own recurrence, slot, duration and capacity ceiling.
 *
 * Capacity lives in both places on purpose. The type carries the published
 * ceiling ("Up to 40 students"); the session carries what is actually offered
 * on the day, which may be lower — a tour booked for one class, not four.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Display labels, straight from §15's table. Deliberately strings
            // rather than a parsed recurrence: "Monday – Friday" with two
            // slots a day and "By Appointment" do not share a machine format,
            // and inventing one would mean rewriting the published programme
            // to fit the schema.
            $table->string('days_label');
            $table->string('slot_label');
            $table->string('duration_label');
            // The published ceiling for the experience.
            $table->unsignedInteger('capacity');
            // True for the three §15 marks "By Appointment" — they have no
            // standing schedule, so the storefront asks for an enquiry rather
            // than offering a seat.
            $table->boolean('requires_appointment')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('workshop_sessions', function (Blueprint $table) {
            // Nullable: sessions created before the programme existed have no
            // type, and dropping them to satisfy a NOT NULL would delete real
            // bookings' parents.
            $table->foreignId('workshop_type_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workshop_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workshop_type_id');
        });

        Schema::dropIfExists('workshop_types');
    }
};
