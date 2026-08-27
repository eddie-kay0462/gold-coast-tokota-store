<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singular table name: "feedbacks" is not a word, and the admin app's
        // FeedbackEntry type already reads as a singular collective.
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            // Nullable: feedback is accepted from signed-out visitors, and the
            // form on /help asks for a name and email rather than a login.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            // 1-5, and nullable — the storefront form doesn't collect one today,
            // but the admin table has a column for it and the brand may want to
            // ask later. Range is enforced at the Form Request layer.
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('message');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
