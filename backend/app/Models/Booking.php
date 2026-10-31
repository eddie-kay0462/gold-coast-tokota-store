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
        'order_id',
        'hold_expires_at',
        'ticket_code',
        'checked_in_at',
        'checked_in_by_admin_id',
        'needs_attention',
    ];

    protected $casts = [
        'details' => 'array',
        'scheduled_date' => 'date',
        'hold_expires_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'needs_attention' => 'boolean',
    ];

    /** Statuses that hold seats: unpaid but held, and every booked one. */
    public const SEAT_HOLDING = ['awaiting_payment', 'pending', 'confirmed', 'completed'];

    /** How many people it's for: at least one, whatever the form sent. */
    public function attendees(): int
    {
        return max(1, (int) ($this->details['attendee_count'] ?? 1));
    }

    /** The paid order behind a paid event booking. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'checked_in_by_admin_id');
    }

    /** `WS-12`, as the admin and the ticket read it. */
    public function reference(): string
    {
        return ($this->type === 'workshop' ? 'WS-' : 'DIY-').$this->id;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workshopSession(): BelongsTo
    {
        return $this->belongsTo(WorkshopSession::class);
    }
}
