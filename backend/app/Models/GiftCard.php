<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A code holding a GHS balance: a gift card someone bought, or store credit an
 * Admin issued. Only `GiftCardService` changes `balance`, always under a row
 * lock and always with a matching `GiftCardTransaction`.
 */
class GiftCard extends Model
{
    public const SOURCE_PURCHASE = 'purchase';

    public const SOURCE_STORE_CREDIT = 'store_credit';

    protected $fillable = [
        'code',
        'source',
        'status',
        'initial_balance',
        'balance',
        'recipient_name',
        'recipient_email',
        'sender_name',
        'message',
        'purchase_order_id',
        'issued_by_admin_id',
        'note',
        'expires_at',
    ];

    protected $casts = [
        'initial_balance' => 'integer',
        'balance' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class)->orderBy('id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'purchase_order_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'issued_by_admin_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Can be spent right now: active, in date, with something left on it. */
    public function isUsable(): bool
    {
        return $this->status === 'active' && ! $this->isExpired() && $this->balance > 0;
    }

    /** Codes are matched however they were typed: case, spaces and dashes aside. */
    /**
     * Matched with the dashes and spaces taken out, on both sides: a code is
     * read aloud and retyped, and a chosen word (GCT-MAMA-7KQ4) doesn't split
     * into the usual groups of four.
     */
    public function scopeWithCode(Builder $query, string $code): Builder
    {
        return $query->whereRaw("REPLACE(code, '-', '') = ?", [self::normalise($code)]);
    }

    public static function normalise(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($code)) ?? '';
    }

    /** Letters and numbers a chosen code word may use, 2 to 10 of them. */
    public const CODE_WORD_RULE = 'regex:/^\s*[A-Za-z0-9]{2,10}\s*$/';

    /** A chosen word as it goes on the card, or null for none. */
    public static function codeWord(?string $word): ?string
    {
        $word = strtoupper(trim((string) $word));

        return $word === '' ? null : $word;
    }

    /** The last four characters, which is all a customer-facing screen shows. */
    public function lastFour(): string
    {
        return substr($this->code, -4);
    }
}
