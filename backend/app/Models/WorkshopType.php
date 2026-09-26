<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the six experiences in §15 of the brand document — the published
 * programme, not an individual date. See the migration for why the schedule is
 * stored as display labels.
 */
class WorkshopType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'days_label',
        'slot_label',
        'duration_label',
        'capacity',
        'requires_appointment',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'requires_appointment' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(WorkshopSession::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
