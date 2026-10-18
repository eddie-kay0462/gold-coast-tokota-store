<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_zone', 16)->nullable()->after('delivery_reference');
            // What the customer chose and how it was priced, frozen at
            // checkout: carrier, service, raw quote, buffer, live or fallback.
            $table->jsonb('shipping_option')->nullable()->after('shipping_zone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_zone', 'shipping_option']);
        });
    }
};
