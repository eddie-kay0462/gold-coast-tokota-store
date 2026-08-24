<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            // Unique — single opt-in has no confirmation step, so the unique
            // constraint IS the de-duplication guarantee (README Testing
            // Checklist: "single opt-in... and de-duplication").
            $table->string('email')->unique();
            $table->timestamp('subscribed_at');
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
