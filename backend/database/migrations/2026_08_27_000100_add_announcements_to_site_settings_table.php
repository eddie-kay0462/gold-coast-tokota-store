<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The storefront's announcement bar rotates a short list of messages.
        // It lives in site settings rather than in the header component because
        // the copy is commercial ("free delivery in Accra", "pay with MoMo") —
        // the kind of claim that changes with a promotion and must not need a
        // deploy, and must not be asserted by developers on the brand's behalf.
        Schema::table('site_settings', function (Blueprint $table) {
            $table->jsonb('announcements')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('announcements');
        });
    }
};
