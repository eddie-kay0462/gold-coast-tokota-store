<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client's catalogue sheets list what each style is made of ("Soft
 * leather", "Foam board", "Welt", …) and the product had nowhere to keep it.
 *
 * Not `tags`: those render as badges on the product card, and a card reading
 * "FOAM BOARD" is not a badge anyone meant. Not `description` either — turning
 * a parts list into sentences is writing copy the brand has not approved.
 *
 * An ordered list of strings, jsonb for the same reason `tags` is: it is
 * displayed, never queried or aggregated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->jsonb('materials')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('materials');
        });
    }
};
