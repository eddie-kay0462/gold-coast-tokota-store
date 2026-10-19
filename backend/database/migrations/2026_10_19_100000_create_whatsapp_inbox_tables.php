<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The WhatsApp inbox: who wrote, the conversation, every message, and the
 * templates Meta has approved.
 *
 * WhatsApp has exactly one thread per customer, so a conversation is one per
 * contact for life. "Closing" it archives it; the customer's next message
 * reopens the same row, and the history stays in one place.
 *
 * Messages arrive from four places, recorded in `source`:
 *   customer   inbound, via the `messages` webhook
 *   agent      typed in the admin inbox
 *   automation the greeting, the after-hours note, status templates
 *   phone_app  typed in the WhatsApp Business app on the brand's phone,
 *              mirrored to us by coexistence (`smb_message_echoes`)
 *   history    imported once at onboarding (coexistence `history`)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            // The customer's number as WhatsApp reports it: digits, no "+".
            $table->string('wa_id')->unique();
            $table->string('phone_e164');
            $table->string('profile_name')->nullable();
            // Matched on phone when a customer record exists; a team member
            // can also link one by hand.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // The customer replied STOP. No automation or template goes to
            // them until they write again and opt back in.
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamps();

            $table->index('customer_id');
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->unique()->constrained('whatsapp_contacts')->cascadeOnDelete();
            $table->string('status')->default('open'); // open | closed
            $table->string('topic')->default('general');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('linked_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('linked_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            // The customer's last message. A free-form reply is allowed for 24
            // hours after it; after that only an approved template.
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 160)->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'last_message_at']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            // WhatsApp's own message id. Null only until an outbound message
            // is accepted. Unique, which is what makes a replayed webhook a
            // no-op.
            $table->string('wamid')->nullable()->unique();
            $table->string('direction'); // inbound | outbound
            $table->string('kind')->default('text'); // text | image | document | audio | video | sticker | location | template | interactive | system
            $table->text('body')->nullable();
            $table->string('media_path')->nullable(); // r2-private, read through MediaStorage
            $table->string('media_mime')->nullable();
            $table->string('media_filename')->nullable();
            $table->string('template_name')->nullable();
            // draft | queued | sent | delivered | read | failed | received
            $table->string('status');
            $table->string('error')->nullable();
            $table->string('source'); // customer | agent | automation | phone_app | history
            $table->string('provider')->nullable(); // cloud_api | log
            $table->foreignId('sent_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'sent_at']);
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('meta_id')->nullable()->unique();
            $table->string('name');
            $table->string('language');
            $table->string('category'); // utility | marketing | authentication
            $table->string('status'); // approved | pending | rejected | paused | disabled
            $table->jsonb('components')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'language']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('whatsapp_conversation_id')
                ->references('id')->on('whatsapp_conversations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_conversation_id']);
        });

        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_contacts');
    }
};
