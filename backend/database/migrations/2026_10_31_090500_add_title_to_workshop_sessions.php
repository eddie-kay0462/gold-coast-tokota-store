<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A session's own name and description, for one run of an experience with a
 * theme of its own ("Halloween Sip & Paint"). Null means it's simply the
 * experience it belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_sessions', function (Blueprint $table) {
            $table->string('title', 120)->nullable()->after('workshop_type_id');
            $table->text('description')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_sessions', function (Blueprint $table) {
            $table->dropColumn(['title', 'description']);
        });
    }
};
