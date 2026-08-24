<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'customer_id',
        'workshop_session_id',
        'scheduled_date',
        'details',
        'status',
    ];

    protected $casts = [
        'details' => 'array',
        'scheduled_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workshopSession(): BelongsTo
    {
        return $this->belongsTo(WorkshopSession::class);
    }
}
