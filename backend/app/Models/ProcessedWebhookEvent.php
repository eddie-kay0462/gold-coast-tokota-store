<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedWebhookEvent extends Model
{
    protected $fillable = ['gateway', 'event_id', 'event_type', 'processed_at'];

    protected $casts = ['processed_at' => 'datetime'];
}
