<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category_id',
        'collection_id',
        'base_price_ghs',
        'sku',
        'images',
        'is_active',
        'is_featured',
        'merchandising_badge',
    ];

    protected $casts = [
        'images' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'base_price_ghs' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Sum of sellable_quantity across all variants — physical stock minus
     * units already held by pending checkouts (Feature 3). Using raw
     * quantity_available here would let the storefront offer units that are
     * actually spoken for by someone else's in-progress payment.
     */
    public function getInStockAttribute(): bool
    {
        return $this->inventoryItems->sum('sellable_quantity') > 0;
    }

    /**
     * Storefront merchandising badge (LIMITED STOCK / BACK IN STOCK / OUT OF
     * STOCK). out_of_stock and limited_stock are always computed from live
     * inventory so they can never go stale; merchandising_badge is only
     * consulted once stock is healthy, since "back in stock" can't be
     * inferred from a quantity alone — it's an editorial call.
     */
    public function getEffectiveBadgeAttribute(): ?string
    {
        if (! $this->in_stock) {
            return 'out_of_stock';
        }

        $sellable = $this->inventoryItems->sum('sellable_quantity');
        $threshold = $this->inventoryItems->sum('low_stock_threshold');

        if ($sellable <= $threshold) {
            return 'limited_stock';
        }

        return $this->merchandising_badge;
    }
}
