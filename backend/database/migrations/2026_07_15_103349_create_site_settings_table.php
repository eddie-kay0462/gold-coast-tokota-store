<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row key/value CMS resource for owner-editable global config
        // (WhatsApp number, hero content, DIY turnaround estimate, etc.).
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_number')->nullable();
            $table->string('whatsapp_default_message')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('hero_headline')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('diy_turnaround_estimate')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
