<?php

namespace App\Http\Resources\Admin;

use App\Http\Controllers\Api\V1\BookingUploadController;
use App\Support\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $details = $this->details ?? [];
        $name = $this->customer?->name ?? ($details['name'] ?? null);
        $email = $this->customer?->email ?? ($details['email'] ?? null);
        $phone = $details['phone'] ?? null;
        $session = $this->relationLoaded('workshopSession') ? $this->workshopSession : null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'customer_id' => $this->customer_id,
            // Bookings are guest-friendly like checkout, so the contact details
            // live in `details` rather than on a Customer row.
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            // The same three under the names the admin's booking screens read.
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            // For reading out and searching on. Bookings have no stored
            // reference; this is the id with its kind in front.
            'reference' => ($this->type === 'workshop' ? 'WS-' : 'DIY-').$this->id,
            'scheduled_date' => $this->scheduled_date?->toDateString() ?? $session?->scheduled_date?->toDateString(),
            'workshop_type_name' => $session?->workshopType?->name,
            // The session's own title when it has one ("Halloween Sip &
            // Paint"), else the experience's name. Bookings group by session.
            'session_title' => $session?->title,
            'event_name' => $session ? ($session->title ?: $session->workshopType?->name) : null,
            'first_name' => $details['first_name'] ?? null,
            'last_name' => $details['last_name'] ?? null,
            'scheduled_slot' => $session?->scheduled_slot,
            'attendee_count' => isset($details['attendee_count']) ? (int) $details['attendee_count'] : null,
            // 1 is next up: the oldest waitlisted booking on the session.
            'waitlist_position' => $this->waitlistPosition(),
            'diy_specs' => $this->type === 'diy_order' ? [
                'size' => $details['size'] ?? null,
                'foot_length_cm' => isset($details['foot_length']) ? (float) $details['foot_length'] : null,
                'preferred_fulfilment' => $details['fulfilment'] ?? null,
            ] : null,
            'workshop_session_id' => $this->workshop_session_id,
            'details' => (object) $details,
            // The reference photo, resolved to something the admin screen can
            // actually open. Null unless `reference_image` holds a path this
            // API issued: bookings submitted before the upload endpoint
            // existed carry the customer's *filename* there instead (the form
            // recorded it and sent the photo over WhatsApp), and turning that
            // into a URL would produce a broken image on every historical
            // booking. Checking the prefix is also what stops an arbitrary
            // string in that field being rendered as a link.
            'reference_image_url' => $this->referenceImageUrl($details),
            'submitted_at' => $this->created_at,
            'created_at' => $this->created_at,
        ];
    }

    private function waitlistPosition(): ?int
    {
        if ($this->status !== 'waitlisted' || $this->workshop_session_id === null) {
            return null;
        }

        return 1 + $this->resource->newQuery()
            ->where('workshop_session_id', $this->workshop_session_id)
            ->where('status', 'waitlisted')
            ->where(fn ($q) => $q->where('created_at', '<', $this->created_at)
                ->orWhere(fn ($q) => $q->where('created_at', $this->created_at)->where('id', '<', $this->id)))
            ->count();
    }

    /** @param  array<string, mixed>  $details */
    private function referenceImageUrl(array $details): ?string
    {
        $path = $details['reference_image'] ?? null;

        if (! is_string($path) || ! str_starts_with($path, BookingUploadController::DIRECTORY.'/')) {
            return null;
        }

        return MediaStorage::privateUrl($path);
    }
}
