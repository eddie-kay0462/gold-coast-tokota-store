<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin post editor (`admin/components/blog/PostEditor.vue`) has fields
 * for an excerpt, cover-image alt text and a meta description, and the table
 * had nowhere to keep any of them.
 *
 * All nullable: existing posts have none, and the editor treats a blank meta
 * description as "use the excerpt".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('excerpt', 500)->nullable()->after('body');
            $table->string('cover_image_alt')->nullable()->after('cover_image');
            $table->string('meta_description', 200)->nullable()->after('cover_image_alt');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['excerpt', 'cover_image_alt', 'meta_description']);
        });
    }
};
