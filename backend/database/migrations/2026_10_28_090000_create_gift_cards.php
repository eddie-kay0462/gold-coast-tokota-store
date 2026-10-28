<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gift cards and store credit: one balance-code system for both.
 *
 * A gift card is bought online through Paystack; store credit is issued by an
 * Admin (§11/§21: a customer whose item is unavailable after purchase may be
 * offered store credit). Either way it is a code holding a balance in GHS
 * minor units that is spent at checkout, and every change to that balance is a
 * row in `gift_card_transactions`, so a balance can always be explained.
 *
 * Off until an Admin switches `gift_cards_enabled` on, with the amounts, any
 * expiry and the terms all set by the client in admin (§22: no invented policy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            // GCT-XXXX-XXXX-XXXX. Kept readable rather than hashed: staff read
            // it back to customers on WhatsApp, and resend it.
            $table->string('code')->unique();
            // 'purchase' | 'store_credit'
            $table->string('source');
            // 'active' | 'disabled'
            $table->string('status')->default('active');
            // GHS minor units, whatever currency it was bought in.
            $table->unsignedBigInteger('initial_balance');
            $table->unsignedBigInteger('balance');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('sender_name')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('purchase_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('issued_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('recipient_email');
        });

        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->cascadeOnDelete();
            // issue | redeem | release | reclaim | adjust
            $table->string('type');
            // Signed GHS minor units: negative takes from the balance.
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('orders', function (Blueprint $table) {
            // 'goods' | 'gift_card'. A gift card order has no stock and no
            // shipment.
            $table->string('kind')->default('goods')->after('reference');
            $table->foreignId('gift_card_id')->nullable()->after('tax')->constrained()->nullOnDelete();
            // What the card took off this order, in the order's currency, and
            // the same in GHS (what came off the card's balance).
            $table->unsignedBigInteger('gift_card_amount')->default(0)->after('gift_card_id');
            $table->unsignedBigInteger('gift_card_amount_ghs')->default(0)->after('gift_card_amount');
            // Set when an unpaid order's hold lapsed and the balance went back
            // to the card. A payment that lands afterwards takes it again.
            $table->timestamp('gift_card_released_at')->nullable()->after('gift_card_amount_ghs');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // On a gift card order's line: the amount (GHS), recipient,
            // message, and the card issued for it once paid.
            $table->jsonb('gift_card')->nullable()->after('currency');
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('gift_cards_enabled')->default(false);
            // GHS minor units, in display order. Empty until the client sets them.
            $table->jsonb('gift_card_amounts')->nullable();
            // Null: gift cards don't expire.
            $table->unsignedSmallInteger('gift_card_expiry_months')->nullable();
            $table->text('gift_card_terms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['gift_cards_enabled', 'gift_card_amounts', 'gift_card_expiry_months', 'gift_card_terms']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('gift_card');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_card_id');
            $table->dropColumn(['kind', 'gift_card_amount', 'gift_card_amount_ghs', 'gift_card_released_at']);
        });

        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_cards');
    }
};
