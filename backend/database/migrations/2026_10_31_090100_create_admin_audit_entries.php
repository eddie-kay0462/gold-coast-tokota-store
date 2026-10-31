<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings › Audit log: one row per change made in the admin, written by
 * RecordAdminAction after the change succeeds. The actor's name and role are
 * copied in, so the log still reads right after an account is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_role');
            // "Updated product", "Deleted workshop session"…
            $table->string('action');
            // The route name, for filtering and for anything the label hides.
            $table->string('route');
            $table->string('target')->nullable();
            $table->text('detail')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_entries');
    }
};
