<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate',
        'fetched_at',
        'source',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'fetched_at' => 'datetime',
    ];

    /** Most recently fetched row — the one live prices are derived from. */
    public function scopeLatestFetched(Builder $query): Builder
    {
        return $query->orderByDesc('fetched_at');
    }
}
