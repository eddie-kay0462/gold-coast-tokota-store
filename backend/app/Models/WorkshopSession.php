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
        'workshop_type_id',
        'title',
        'description',
        'scheduled_date',
        'scheduled_slot',
        'capacity',
        'price_ghs',
        'location_notes',
        'created_by_admin_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'capacity' => 'integer',
        'price_ghs' => 'integer',
    ];

    public function workshopType(): BelongsTo
    {
        return $this->belongsTo(WorkshopType::class);
    }

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

    /**
     * People holding a seat: every booked party, and a paid one still within
     * its payment hold. Counted in people, not bookings: a booking for four
     * takes four seats. (It used to count bookings, so a session could fill
     * past its capacity.) Waitlisted and cancelled bookings hold none.
     */
    public function getOccupiedSeatsAttribute(): int
    {
        return $this->bookings()
            ->whereIn('status', Booking::SEAT_HOLDING)
            ->get(['id', 'details'])
            ->sum(fn (Booking $booking) => $booking->attendees());
    }

    /** Price per person in pesewas: this date's own, else its event's. Null is free. */
    public function priceGhs(): ?int
    {
        $price = $this->price_ghs ?? $this->workshopType?->price_ghs;

        return $price ? (int) $price : null;
    }

    /** What to call it: its own title, or its event's name. */
    public function eventName(): ?string
    {
        return $this->title ?: $this->workshopType?->name;
    }

    public function getRemainingCapacityAttribute(): int
    {
        return max(0, $this->capacity - $this->occupied_seats);
    }
}
