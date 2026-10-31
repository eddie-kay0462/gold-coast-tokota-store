<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One change made in the admin. Written by RecordAdminAction, never edited. */
class AdminAuditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_user_id',
        'actor_name',
        'actor_role',
        'action',
        'route',
        'target',
        'detail',
        'ip',
    ];
}
