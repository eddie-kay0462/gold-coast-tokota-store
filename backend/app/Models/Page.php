<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'body',
        'updated_by_admin_id',
    ];

    public function updatedByAdmin()
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }
}
