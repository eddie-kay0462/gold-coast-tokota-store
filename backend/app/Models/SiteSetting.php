<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'whatsapp_number',
        'whatsapp_default_message',
        'contact_email',
        'contact_phone',
        'instagram_url',
        'hero_headline',
        'hero_image',
        'diy_turnaround_estimate',
        'announcements',
    ];

    protected $casts = [
        'announcements' => 'array',
    ];

    /** Single-row resource — creates the row on first access if missing. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
