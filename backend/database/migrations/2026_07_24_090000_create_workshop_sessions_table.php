<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('scheduled_date');
            // e.g. "10:00 - 13:00" — a display label, not a machine-parsed
            // range; the workshop is a single fixed block, not open slots.
            $table->string('scheduled_slot');
            $table->unsignedInteger('capacity');
            $table->text('location_notes')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->index('scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_sessions');
    }
};
