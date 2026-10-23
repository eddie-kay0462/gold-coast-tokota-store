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

    public function updatedByAdmin()
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }
}
