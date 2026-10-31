<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds sent from the admin through Paystack (OrderRefunds). What has gone
 * back so far, in the order's currency, so a second refund can't return more
 * than was paid; and whether the gift card part went back to its card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('gift_card_refunded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refunded_amount', 'refunded_at', 'gift_card_refunded_at']);
        });
    }
};
