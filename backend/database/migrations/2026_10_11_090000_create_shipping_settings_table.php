<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single row, like site_settings: the shipping knobs an Admin turns
        // from the dashboard rather than by redeploying.
        Schema::create('shipping_settings', function (Blueprint $table) {
            $table->id();
            // GeoJSON Polygon or MultiPolygon, [lng, lat] pairs. Null means
            // there is no Accra zone at all and every Ghana order is flat-rate.
            $table->jsonb('accra_polygon')->nullable();
            // GHS minor units. Null means off. Not stated in the brand
            // document, so it ships off — see FOR_THE_TEAM.md.
            $table->unsignedBigInteger('free_shipping_threshold_ghs')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_settings');
    }
};
