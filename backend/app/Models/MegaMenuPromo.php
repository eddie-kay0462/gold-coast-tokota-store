<?php

namespace App\Models;

use App\Support\MegaMenus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One chosen image tile in a storefront mega menu. See App\Support\MegaMenus. */
class MegaMenuPromo extends Model
{
    protected $fillable = ['menu', 'position', 'media_asset_id', 'caption', 'link'];

    protected $casts = ['position' => 'integer'];

    protected static function booted(): void
    {
        static::saved(fn () => MegaMenus::forget());
        static::deleted(fn () => MegaMenus::forget());
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
