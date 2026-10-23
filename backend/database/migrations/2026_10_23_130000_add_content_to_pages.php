<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured content for a page whose layout is designed section by section:
 * today only the About page (App\Support\AboutPage). Null means "all
 * defaults", so nothing needs seeding and the page renders unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->jsonb('content')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
