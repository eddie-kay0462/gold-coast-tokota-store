<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Both of these were already declared in the admin app's `SiteSettings`
        // type and already bound to inputs on its WhatsApp settings screen —
        // the API simply never returned them, so the fields sat empty.
        //
        // `whatsapp_greeting` is the brand guidelines' "Default Greeting
        // Message": the auto-reply the business sends when someone opens a
        // chat. The storefront never renders it; it lives here so the owner
        // manages one copy of it in one place rather than only inside the
        // WhatsApp Business app.
        //
        // `business_hours` is rendered — the announcement bar's second line
        // tells people when to expect an answer, and had it hardcoded.
        Schema::table('site_settings', function (Blueprint $table) {
            $table->text('whatsapp_greeting')->nullable();
            $table->string('business_hours')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_greeting', 'business_hours']);
        });
    }
};
