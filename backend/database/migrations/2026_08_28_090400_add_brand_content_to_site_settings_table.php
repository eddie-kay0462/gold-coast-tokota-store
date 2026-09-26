<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand facts the document publishes and no table held: the address (§12),
 * the trading hours and default greeting (§14) and the tagline (§24).
 *
 * All four are already rendered somewhere in the storefront as hard-coded
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
            // §14: "Monday – Saturday: 9:00 AM – 5:00 PM (GMT)". A display
            // string, not a parsed schedule — nothing opens or closes on it,
            // it is what a customer is told when they get in touch.
            $table->string('business_hours')->nullable();
            // §14's default greeting, in full. Distinct from
            // whatsapp_default_message, which is what the *customer* sends;
            // this is what the business replies with.
            $table->text('greeting_message')->nullable();
            // §24: "Crafted with Purpose. Inspired by Culture."
            $table->string('tagline')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['address', 'business_hours', 'greeting_message', 'tagline']);
        });
    }
};
