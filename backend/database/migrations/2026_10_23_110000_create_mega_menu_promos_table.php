<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two image tiles in each storefront mega menu, chosen in the admin
 * (Content › Menus). A menu with no row for a slot shows its newest product
 * photo there instead, so this table can start, and stay, empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mega_menu_promos', function (Blueprint $table) {
            $table->id();
            // A key from App\Support\MegaMenus, e.g. `sandals`.
            $table->string('menu', 40);
            $table->unsignedTinyInteger('position');
            // Deleting the image from the media library empties the slot
            // rather than leaving a tile with no picture.
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('caption', 80);
            // An in-site path such as /shop?type=ahenema.
            $table->string('link', 255);
            $table->timestamps();

            $table->unique(['menu', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mega_menu_promos');
    }
};
