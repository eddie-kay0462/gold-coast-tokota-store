<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Returns and exchanges, per §9 and §21 of the brand document.
 *
 * The admin dashboard has had a Returns screen since it was built, running on
 * fixtures, because nothing in the README covers returns and there was no way
 * to guess the rules. §9 supplies all of them: a seven-day window from
 * *receipt*, the three accepted reasons, the four non-returnable categories,
 * and who pays return shipping on a size exchange.
 *
 * There is no customer-facing endpoint by design. §9 gives one route in for a
 * return — "Return / Exchange Contact: WhatsApp" — so a request arrives as a
 * message and staff record it here. A self-service returns portal would be new
 * scope, and §22.2 says not to invent policy that isn't written down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // 'defective' | 'wrong_item' | 'damaged_in_transit' | 'size_exchange'
            // — the first three are §9's accepted return reasons, the fourth is
            // its separate exchange path. Validated in the Form Request, not a
            // DB enum, so a fifth reason never needs a migration.
            $table->string('reason');
            // 'requested' | 'approved' | 'received' | 'refunded' | 'exchanged' | 'rejected'
            $table->string('status')->default('requested');

            // Frozen at the moment the request is recorded, not recomputed on
            // read. Eligibility is a decision made against the policy as it
            // stood that day, and staff can override it — a later policy change
            // must not silently rewrite a call someone already made.
            $table->boolean('is_eligible');
            $table->string('ineligible_reason')->nullable();

            // Null for a size exchange: nothing is refunded, the pair is
            // swapped. Minor units in the order's currency, like every other
            // money column.
            $table->unsignedBigInteger('refund_amount')->nullable();
            // deliveredAt + 7 days (§9). Stored rather than derived because
            // the delivery date it was measured from can be corrected later.
            $table->timestamp('window_closes_at')->nullable();

            $table->timestamp('requested_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_admin_id')->nullable()
                ->constrained('admin_users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
