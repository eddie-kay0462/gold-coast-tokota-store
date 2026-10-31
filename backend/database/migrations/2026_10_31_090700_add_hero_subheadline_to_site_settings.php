<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The line under the home hero's headline, editable in Settings › General like
 * the headline. Null keeps the storefront's built-in line ("Get your pair of
 * authentic locally-made Ghanaian footwear.").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('hero_subheadline')->nullable()->after('hero_headline');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', fn (Blueprint $table) => $table->dropColumn('hero_subheadline'));
    }
};
