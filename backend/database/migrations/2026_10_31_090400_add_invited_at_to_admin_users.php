<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A new team member is invited by email and sets their own password
 * (AdminInvites). Set when the invitation goes, cleared once they've chosen
 * a password; while it's set, the account can't sign in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn('invited_at');
        });
    }
};
