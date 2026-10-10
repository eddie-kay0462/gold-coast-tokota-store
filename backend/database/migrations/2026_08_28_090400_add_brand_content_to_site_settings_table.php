<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand facts the document publishes and no table held: the address (§12)
 * and the tagline (§24). §14's trading hours and default greeting are
 * `business_hours` and `whatsapp_greeting`, added by the 2026_08_27_000200
 * migration from the WhatsApp work on `main`.
 *
 * Both are already rendered somewhere in the storefront as hard-coded
 * strings. Moving them here makes each one a settings edit rather than a
 * deploy — which matters most for the two the document flags as provisional:
 * §12 says the phone number "should be updated with the official number", and
 * a number a developer has to change is a number that stays wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // §12: "Haatso, Accra, Ghana."
            $table->string('address')->nullable();
            // §24: "Crafted with Purpose. Inspired by Culture."
            $table->string('tagline')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['address', 'tagline']);
        });
    }
};
