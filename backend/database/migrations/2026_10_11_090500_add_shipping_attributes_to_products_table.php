<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a courier and a customs officer need to know about a product.
        // Integers in grams and millimetres; carriers convert at their own
        // edge. Per product, not per variant: every size ships in the same
        // box. International checkout is refused for a product missing any of
        // weight, dimensions or HS code while DHL is switched on.
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            // Harmonized System code for the export declaration, e.g. 6403.99.
            $table->string('hs_code', 12)->nullable();
            $table->char('origin_country', 2)->default('GH');
            // What customs reads: "Leather sandals", not a marketing name.
            $table->string('customs_description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'weight_grams', 'length_mm', 'width_mm', 'height_mm',
                'hs_code', 'origin_country', 'customs_description',
            ]);
        });
    }
};
