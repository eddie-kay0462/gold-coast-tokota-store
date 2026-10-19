<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** The one thread between the brand and one contact. */
class WhatsAppConversation extends Model
{
    public const TOPICS = ['order', 'booking', 'diy_order', 'returns', 'wholesale', 'general'];

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'contact_id',
        'status',
        'topic',
        'assigned_admin_id',
        'linked_order_id',
        'linked_booking_id',
        'last_inbound_at',
        'last_message_at',
        'last_message_preview',
        'unread_count',
    ];

    protected $casts = [
        'last_inbound_at' => 'datetime',
        'last_message_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_admin_id');
    }

    public function linkedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'linked_order_id');
    }

    public function linkedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'linked_booking_id');
    }

    /** When free-form replies stop being allowed. Null if the customer has never written. */
    public function serviceWindowClosesAt(): ?Carbon
    {
        return $this->last_inbound_at?->copy()->addHours((int) config('whatsapp.service_window_hours', 24));
    }

    public function serviceWindowOpen(): bool
    {
        return (bool) $this->serviceWindowClosesAt()?->isFuture();
    }
}
