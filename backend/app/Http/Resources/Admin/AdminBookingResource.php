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
        ];
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
