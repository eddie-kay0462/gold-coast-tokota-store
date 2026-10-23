<?php

use App\Support\PersonName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customers get a first and a last name. Sign-up asks for them separately, so
 * they are stored separately.
 *
 * `name` stays, as the full name, kept in step by the Customer model on every
 * save. Everything that shows a customer's whole name (notifications, courier
 * labels, the inbox, admin search) goes on reading it unchanged.
 *
 * Existing rows are split at the first space: "Ama Serwaa Mensah" becomes
 * Ama / Serwaa Mensah. A one-word name leaves `last_name` empty, which is why
 * it is nullable: the API requires it for new sign-ups, not for history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
        });

        DB::table('customers')->select('id', 'name')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                [$first, $last] = PersonName::split((string) $row->name);
                DB::table('customers')->where('id', $row->id)->update(['first_name' => $first, 'last_name' => $last]);
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
