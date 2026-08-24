<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('collection_id')->nullable()->after('category_id')->constrained()->nullOnDelete();

            // OUT_OF_STOCK and LIMITED_STOCK are always computed from live
            // InventoryItem quantities (see Product::getEffectiveBadgeAttribute)
            // — never stored, never stale. This column exists only for the one
            // state that can't be inferred from a quantity: BACK_IN_STOCK is an
            // editorial call ("we just restocked"), so admin/staff set it
            // explicitly here. Nullable string rather than a DB enum so new
            // badge values (e.g. "NEW") don't need a migration — validated at
            // the Form Request layer instead.
            $table->string('merchandising_badge')->nullable()->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collection_id');
            $table->dropColumn('merchandising_badge');
        });
    }
};
