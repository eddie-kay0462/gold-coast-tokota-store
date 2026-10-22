<?php

namespace App\Models;

use App\Support\OrderReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A storefront cart carried into a WhatsApp chat. Holds no stock. */
class WhatsAppOrderDraft extends Model
{
    /** How long a code stays good for: long enough for a customer who comes back tomorrow. */
    public const LIFETIME_DAYS = 7;

    /** e.g. WA-7KQ2MP: short enough to read aloud, unambiguous to type. */
    public const PATTERN = '/\bWA-([A-HJ-NP-Z2-9]{6})\b/i';

    protected $table = 'whatsapp_order_drafts';

    protected $fillable = ['reference', 'items', 'currency', 'conversation_id', 'converted_order_id', 'expires_at'];

    protected $casts = [
        'items' => 'array',
        'expires_at' => 'datetime',
    ];

    public static function newReference(): string
    {
        return OrderReference::generate('WA-', 6, fn (string $ref) => static::query()->where('reference', $ref)->exists());
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    /** Not yet an order, and not expired. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('converted_order_id')->where('expires_at', '>', now());
    }
}
