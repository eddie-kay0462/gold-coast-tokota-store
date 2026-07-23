<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Separate from `categories` — category is the top-nav split (e.g.
        // "Sandals" vs "Ahenema"), collection is the merchandising grouping
        // within it (e.g. "Sikapa", "Obrempong", a named seasonal drop). A
        // product has at most one of each, independently.
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
