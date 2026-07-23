<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3)->default('GHS');
            $table->string('quote_currency', 3)->default('USD');
            $table->decimal('rate', 12, 6);
            $table->timestamp('fetched_at');
            $table->string('source');
            $table->timestamps();

            $table->index('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
