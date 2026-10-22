<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A person who has written to (or been written to by) the brand on WhatsApp. */
class WhatsAppContact extends Model
{
    protected $table = 'whatsapp_contacts';

    protected $fillable = ['wa_id', 'phone_e164', 'profile_name', 'customer_id', 'opted_out_at'];

    protected $casts = ['opted_out_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(WhatsAppConversation::class, 'contact_id');
    }

    public function displayName(): string
    {
        return $this->customer?->name ?: ($this->profile_name ?: $this->phone_e164);
    }
}
