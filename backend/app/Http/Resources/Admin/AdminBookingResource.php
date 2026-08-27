<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $details = $this->details ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'customer_id' => $this->customer_id,
            // Bookings are guest-friendly like checkout, so the contact details
            // live in `details` rather than on a Customer row.
            'name' => $this->customer?->name ?? ($details['name'] ?? null),
            'email' => $this->customer?->email ?? ($details['email'] ?? null),
            'phone' => $details['phone'] ?? null,
            'scheduled_date' => $this->scheduled_date,
            'workshop_session_id' => $this->workshop_session_id,
            'details' => (object) $details,
            'submitted_at' => $this->created_at,
        ];
    }
}
