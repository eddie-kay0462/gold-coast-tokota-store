<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-order-type DIY turnaround estimates, for the admin Workshops screen.
 *
 * The existing `diy_turnaround_estimate` string stays: the storefront's DIY
 * order form quotes one figure and has no order-type selector to choose a tier
 * with. This column is the richer version admin edits; the single estimate
 * remains what the form shows until the form grows a type selector.
 *
 * jsonb rather than a table, following `announcements`: these are five lines of
 * editorial copy the brand rewrites, never queried or joined.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->jsonb('diy_turnaround_tiers')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('diy_turnaround_tiers');
        });
    }
};
