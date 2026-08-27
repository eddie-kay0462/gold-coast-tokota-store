<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'path',
        'filename',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'alt_text',
        'uploaded_by_admin_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'uploaded_by_admin_id');
    }

    /**
     * Derived, never stored. The public disk's URL is built from APP_URL, which
     * differs between local, staging and production — a stored absolute URL
     * would keep pointing at whichever host happened to upload the file.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
