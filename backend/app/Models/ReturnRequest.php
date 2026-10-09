<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A return or exchange recorded against an order (§9). See the migration for
 * why there is no customer-facing endpoint that creates one.
 */
class ReturnRequest extends Model
{
    use HasFactory;

    /** Terminal states — a resolved request is not reopened, a new one is raised. */
    public const RESOLVED_STATUSES = ['refunded', 'exchanged', 'rejected'];

    public const STATUSES = ['requested', 'approved', 'received', ...self::RESOLVED_STATUSES];

    protected $fillable = [
        'order_id',
        'reason',
        'status',
        'is_eligible',
        'ineligible_reason',
        'refund_amount',
        'window_closes_at',
        'requested_at',
        'resolved_at',
        'resolved_by_admin_id',
        'notes',
    ];

    protected $casts = [
        'is_eligible' => 'boolean',
        'refund_amount' => 'integer',
        'window_closes_at' => 'datetime',
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function resolvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'resolved_by_admin_id');
    }
}
