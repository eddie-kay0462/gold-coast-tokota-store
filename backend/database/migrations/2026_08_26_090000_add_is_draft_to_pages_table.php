<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a CMS page as unreviewed placeholder copy.
     *
     * The storefront renders a "draft — awaiting review" banner above any page
     * that is flagged here. Without the column the frontend could only infer
     * draft status from a failed fetch, which meant that seeding placeholder
     * legal copy would *suppress* the banner — the fetch would succeed and the
     * site would publish unreviewed policy text with no warning.
     *
     * Defaults to true so a newly created page is never live by accident. The
     * owner clears it from admin when the copy has actually been reviewed.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('is_draft')->default(true)->after('body');
        });

        // The About page's copy is already live on the storefront.
        DB::table('pages')->where('slug', 'about')->update(['is_draft' => false]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }
};
