<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paid events. An event (workshop type) can carry a price per person, which a
 * session can override; null means free. A paid booking is paid through an
 * Order of kind `booking`, holds its seats while the customer pays, and gets
 * a ticket with a code staff check in against.
 *
 * `allow_waitlist` decides whether a full session takes waitlist bookings or
 * is sold out. New events start without one, as the client asked; the six
 * that existed before keep the waitlist they always had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_types', function (Blueprint $table) {
            $table->unsignedInteger('price_ghs')->nullable()->after('capacity');
            $table->boolean('allow_waitlist')->default(false)->after('price_ghs');
        });
        DB::table('workshop_types')->update(['allow_waitlist' => true]);

        Schema::table('workshop_sessions', function (Blueprint $table) {
            $table->unsignedInteger('price_ghs')->nullable()->after('capacity');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('workshop_session_id')->constrained()->nullOnDelete();
            $table->timestamp('hold_expires_at')->nullable()->after('status');
            $table->string('ticket_code', 16)->nullable()->unique()->after('hold_expires_at');
            $table->timestamp('checked_in_at')->nullable()->after('ticket_code');
            $table->foreignId('checked_in_by_admin_id')->nullable()->after('checked_in_at')->constrained('admin_users')->nullOnDelete();
            $table->boolean('needs_attention')->default(false)->after('checked_in_by_admin_id');
            $table->index(['status', 'hold_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'hold_expires_at']);
            $table->dropConstrainedForeignId('checked_in_by_admin_id');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['hold_expires_at', 'ticket_code', 'checked_in_at', 'needs_attention']);
        });
        Schema::table('workshop_sessions', fn (Blueprint $table) => $table->dropColumn('price_ghs'));
        Schema::table('workshop_types', fn (Blueprint $table) => $table->dropColumn(['price_ghs', 'allow_waitlist']));
    }
};
