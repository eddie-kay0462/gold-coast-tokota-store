<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Semi-automated WhatsApp messaging.
 *
 * 1. Automatic replies on `site_settings`: the §14 greeting on a first
 *    message, and a note outside business hours. **Both start off.** With
 *    coexistence the WhatsApp Business app on the brand phone may already
 *    send its own greeting and away messages, and turning these on as well
 *    would greet customers twice. An Admin switches them on once the phone's
 *    are off.
 * 2. `whatsapp_hours`: the hours the after-hours note keys off, structured
 *    (the `business_hours` string is display copy). Seeded with §14:
 *    "Monday – Saturday: 9:00 AM – 5:00 PM (GMT)".
 * 3. `quick_replies`: saved replies the team inserts with one click, with
 *    slots the server fills from the chat (the customer's name, their linked
 *    order, the next workshop, the published windows). Seeded with a few
 *    whose wording is the brand document's own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_auto_greeting')->default(false);
            $table->boolean('whatsapp_after_hours_enabled')->default(false);
            $table->text('whatsapp_after_hours_message')->nullable();
            $table->jsonb('whatsapp_hours')->nullable();
        });

        DB::table('site_settings')->update([
            'whatsapp_hours' => json_encode(['days' => [1, 2, 3, 4, 5, 6], 'open' => '09:00', 'close' => '17:00']),
        ]);

        Schema::create('quick_replies', function (Blueprint $table) {
            $table->id();
            $table->string('title', 60);
            // Typed after "/" in the reply box, e.g. /hours.
            $table->string('shortcut', 30)->nullable()->unique();
            $table->text('body');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $seed = [
            ['Checking now', 'check', 'Thank you for reaching out to Gold Coast Tokota, {customer.first_name}. Let me check that for you now.'],
            ['Order status', 'status', 'Your order {order.reference} is {order.status}.'],
            ['Delivery times', 'delivery', 'Orders are processed within 48 hours after payment confirmation. Delivery to {delivery.region} takes {delivery.estimate}.'],
            ['Returns window', 'returns', 'You can return or exchange an item within {returns.window} of receiving your order.'],
            ['Next workshop', 'workshop', 'The next workshop is {session.next}.'],
            ['Business hours', 'hours', 'Our team typically responds during business hours: {hours}.'],
        ];

        foreach ($seed as $i => [$title, $shortcut, $body]) {
            DB::table('quick_replies')->insert([
                'title' => $title, 'shortcut' => $shortcut, 'body' => $body,
                'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_replies');

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_auto_greeting', 'whatsapp_after_hours_enabled', 'whatsapp_after_hours_message', 'whatsapp_hours']);
        });
    }
};
