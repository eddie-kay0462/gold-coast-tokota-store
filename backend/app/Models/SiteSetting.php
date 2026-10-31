<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'whatsapp_number',
        'whatsapp_default_message',
        'whatsapp_greeting',
        'business_hours',
        'whatsapp_hold_hours',
        'whatsapp_auto_greeting',
        'whatsapp_after_hours_enabled',
        'whatsapp_after_hours_message',
        'whatsapp_hours',
        'contact_email',
        'contact_phone',
        'address',
        'tagline',
        'instagram_url',
        'hero_headline',
        'hero_subheadline',
        'hero_image',
        'diy_turnaround_estimate',
        'diy_turnaround_tiers',
        'announcements',
        'gift_cards_enabled',
        'gift_card_amounts',
        'gift_card_expiry_months',
        'gift_card_terms',
        'notification_channels',
    ];

    protected $casts = [
        'announcements' => 'array',
        'diy_turnaround_tiers' => 'array',
        'whatsapp_auto_greeting' => 'boolean',
        'whatsapp_after_hours_enabled' => 'boolean',
        'whatsapp_hours' => 'array',
        'gift_cards_enabled' => 'boolean',
        'gift_card_amounts' => 'array',
        'gift_card_expiry_months' => 'integer',
        'notification_channels' => 'array',
    ];

    /** Single-row resource — creates the row on first access if missing. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
