<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order on WhatsApp.
 *
 * 1. `whatsapp_order_drafts`: a cart a customer carried from the storefront
 *    into a WhatsApp chat. The storefront saves it and puts its short code
 *    in the message ("Order code: WA-7KQ2MP"); when the message arrives,
 *    the code links the chat to the draft, and the team builds the order
 *    from it. A draft **holds no stock**: the endpoint is public, and one
 *    that held stock for hours would let anyone empty the shelf. Stock is
 *    held when a team member turns it into an order.
 * 2. `site_settings.whatsapp_hold_hours`: how long a WhatsApp order holds
 *    its stock while the customer pays. The brand document sets no figure,
 *    so it is the owner's to choose (default 24, the length of WhatsApp's
 *    own reply window). Web checkout stays at 15 minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_order_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            // [{inventory_item_id, quantity}], re-priced whenever it is read.
            $table->jsonb('items');
            $table->string('currency', 3)->default('GHS');
            $table->foreignId('conversation_id')->nullable()->constrained('whatsapp_conversations')->nullOnDelete();
            $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['conversation_id', 'converted_order_id']);
            $table->index('expires_at');
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('whatsapp_hold_hours')->default(24);
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('whatsapp_hold_hours');
        });

        Schema::dropIfExists('whatsapp_order_drafts');
    }
};
