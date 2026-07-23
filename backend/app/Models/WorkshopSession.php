<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkshopSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheduled_date',
        'scheduled_slot',
        'capacity',
        'location_notes',
        'created_by_admin_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'capacity' => 'integer',
    ];

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_date', '>=', now()->toDateString());
    }

    /** Bookings that hold a seat — excludes waitlisted (never held one) and cancelled (released it). */
    public function getOccupiedSeatsAttribute(): int
    {
        return $this->bookings()->whereIn('status', ['pending', 'confirmed', 'completed'])->count();
    }

    public function getRemainingCapacityAttribute(): int
    {
        return max(0, $this->capacity - $this->occupied_seats);
    }
}
