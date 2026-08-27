<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The media library (admin `/media`).
 *
 * Not in the README, but it closes a gap in something that is: `products.images`
 * is a jsonb array of paths with no way to put a path into it. Until this
 * exists, the only way to give a product a photo is to edit seed data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            // The stored path on the public disk, e.g. media/2026/08/xyz.jpg.
            // The public URL is derived from it, never stored: the disk's URL
            // changes with APP_URL between environments, and a stored absolute
            // URL would point at localhost forever after one bad seed.
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            // Null for non-raster files; images get both.
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // Accessibility is not optional, but an empty string is honest for
            // a decorative image — so nullable rather than required.
            $table->string('alt_text')->nullable();
            $table->foreignId('uploaded_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
