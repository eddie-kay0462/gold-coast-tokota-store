<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens `admin_users.role` from the two-value enum to the four tiers the
 * system actually has, and gives the time-boxed one an expiry.
 *
 * The brand document (§17/§18) is the source of truth here and names three:
 * Super Admin, Admin, Staff. `intern` is the fourth the business asked for and
 * the admin dashboard already ships — the document does not contradict it, so
 * it is kept. Until this migration the server could not enforce what the UI
 * presented: a Super Admin could not exist at all, and every "Super Admin only"
 * screen in the dashboard was gated on nothing.
 *
 * The column becomes a plain string rather than a wider enum. The tiers are an
 * application concept — see `App\Support\AdminCapability`, which has to be
 * edited anyway when one is added — and a DB enum means a migration every time
 * that happens, on a table with five rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Postgres implements Laravel's enum() as a varchar plus a CHECK
        // constraint, and the constraint outlives a type change — so a
        // ->change() alone would still reject 'super_admin' at write time.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE admin_users DROP CONSTRAINT IF EXISTS admin_users_role_check');
        }

        Schema::table('admin_users', function (Blueprint $table) {
            $table->string('role')->default('staff')->change();

            // Only ever set for `intern`. Null means access does not lapse,
            // which is every other tier.
            $table->timestamp('access_expires_at')->nullable()->after('role');

            // The audit trail the dashboard's Roles & access screen renders:
            // who extended an intern's access, from when, to when. jsonb
            // rather than a table — it is written a handful of times a year,
            // never queried across rows, and only ever read alongside the
            // account it belongs to.
            $table->jsonb('access_extensions')->default('[]');

            // §17 of the brand document lists a job title against every
            // person ("Founder & CEO", "Operations Lead", ...). The role is
            // what the system enforces; the title is who they are on the team,
            // and the dashboard shows both.
            $table->string('job_title')->nullable()->after('name');
            $table->string('avatar')->nullable()->after('job_title');

            // Set on login. The team screen shows it, and a dormant account
            // with standing access is worth being able to see.
            $table->timestamp('last_active_at')->nullable();
        });
    }

    public function down(): void
    {
        // Anything the two-value enum cannot hold has to land somewhere before
        // the column narrows, or the constraint fails on existing rows.
        DB::table('admin_users')->where('role', 'super_admin')->update(['role' => 'admin']);
        DB::table('admin_users')->where('role', 'intern')->update(['role' => 'staff']);

        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['access_expires_at', 'access_extensions', 'job_title', 'avatar', 'last_active_at']);
            $table->enum('role', ['admin', 'staff'])->change();
        });
    }
};
