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
        'compare_at_ghs',
        'sku',
        'images',
        'is_active',
        'is_featured',
        'is_pre_order',
        'merchandising_badge',
        'product_type',
        'departments',
        'widths',
        'tags',
        'color',
        'colors',
        'description_heading',
        'model_note',
        'cost_breakdown',
    ];

    protected $casts = [
        'images' => 'array',
        'departments' => 'array',
        'widths' => 'array',
        'tags' => 'array',
        'colors' => 'array',
        'cost_breakdown' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_pre_order' => 'boolean',
        'base_price_ghs' => 'integer',
        'compare_at_ghs' => 'integer',
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
     * Per-size sellable stock, keyed by the `size` in each variant's
     * `variant_attributes` — e.g. `['40' => 3, '41' => 0]`.
     *
     * The storefront needs this per size, not aggregated: the product card and
     * the purchase panel both strike through the sizes that cannot be bought,
     * and `in_stock` alone cannot tell them which ones those are. Sellable
     * (available minus reserved) for the same reason `in_stock` uses it —
     * offering a unit held by someone else's in-progress payment is worse than
     * showing it as gone.
     *
     * Variants carrying no `size` are skipped rather than bucketed under an
     * empty key; a product with no sized variants returns an empty map, which
     * the frontend reads as "this product has no size axis".
     *
     * @return array<string, int>
     */
    public function getSizeAvailabilityAttribute(): array
    {
        $map = [];

        foreach ($this->inventoryItems as $item) {
            $size = $item->variant_attributes['size'] ?? null;

            if ($size === null || $size === '') {
                continue;
            }

            $size = (string) $size;
            $map[$size] = ($map[$size] ?? 0) + $item->sellable_quantity;
        }

        // Natural sort so 5, 40, 41 order as sizes rather than as strings.
        uksort($map, static fn ($a, $b) => strnatcmp($a, $b));

        return $map;
    }

    /**
     * Every size this product is made in, in order — including the ones that
     * are currently out of stock, which still render (struck through) so the
     * customer can see the range and ask about a restock.
     *
     * Cast back to strings: PHP silently converts numeric array keys to ints,
     * and the storefront's size facet compares these against string values
     * (frontend/pages/shop/index.vue) — an int list matches nothing, silently.
     *
     * @return array<int, string>
     */
    public function getSizesAttribute(): array
    {
        return array_map(strval(...), array_keys($this->size_availability));
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
