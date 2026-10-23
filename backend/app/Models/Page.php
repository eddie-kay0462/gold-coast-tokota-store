<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'body',
        'content',
        'is_draft',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_draft' => 'boolean',
            // Structured sections; only About uses it (App\Support\AboutPage).
            'content' => 'array',
        ];
    }

    /**
     * Admin routes take a page by id or by slug: the editor links by slug
     * (/pages/privacy), and the API's tests and older callers use the id.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return ctype_digit((string) $value)
            ? static::query()->whereKey($value)->first()
            : static::query()->where('slug', $value)->first();
    }

    public function updatedByAdmin()
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }
}
