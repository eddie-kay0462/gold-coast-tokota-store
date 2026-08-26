<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'body',
        'is_draft',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_draft' => 'boolean',
        ];
    }

    public function updatedByAdmin()
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }
}
