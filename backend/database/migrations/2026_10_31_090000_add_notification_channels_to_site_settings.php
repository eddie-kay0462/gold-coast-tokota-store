<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which channels each customer message goes out on, set in Settings ›
 * Notifications: `{ "order_placed": { "email": true, "sms": false }, … }`.
 * Null, or a trigger missing from it, means both on, which is how every
 * message has gone out so far.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->jsonb('notification_channels')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('notification_channels');
        });
    }
};
