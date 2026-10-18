<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A flat delivery rate for one Ghana region, charged to GHANA-zone orders
 * (in Ghana, outside the Accra polygon). Edited by Admin; Staff cannot price.
 */
class ShippingZoneRate extends Model
{
    use HasFactory;

    /**
     * Ghana's sixteen administrative regions, as the checkout form spells
     * them. A region typed any other way does not match and is not offered a
     * rate, which is safer than guessing.
     */
    public const REGIONS = [
        'Ahafo', 'Ashanti', 'Bono', 'Bono East', 'Central', 'Eastern',
        'Greater Accra', 'North East', 'Northern', 'Oti', 'Savannah',
        'Upper East', 'Upper West', 'Volta', 'Western', 'Western North',
    ];

    protected $fillable = [
        'region',
        'amount_ghs',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'amount_ghs' => 'integer',
        'is_active' => 'boolean',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by');
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** The active rate for a region name, matched case-insensitively. */
    public static function forRegion(?string $region): ?self
    {
        $region = trim((string) $region);

        if ($region === '') {
            return null;
        }

        return static::query()
            ->active()
            ->whereRaw('LOWER(region) = ?', [mb_strtolower($region)])
            ->first();
    }
}
