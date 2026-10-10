<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingUploadRequest;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;

/**
 * Reference-photo upload for Design-Your-Own orders (README Feature 7).
 *
 * Before this, `details.reference_image` was typed as a string with nothing
 * behind it: the booking form recorded the *filename* and asked the customer
 * to send the actual photo over WhatsApp. That worked, but it put the one
 * piece of information the workshop needs to make the sandal on a different
 * channel from the order it belongs to.
 *
 * The flow is two steps rather than a multipart booking submission: upload
 * here, then send the returned `path` as `details.reference_image` when the
 * booking is created. That keeps `POST /bookings` a plain JSON endpoint, and
 * means a customer who abandons the form halfway has left a file behind rather
 * than a half-made booking — see PruneOrphanedBookingUploads for what happens
 * to those.
 */
class BookingUploadController extends Controller
{
    /** Where reference photos live, and the prefix the prune job scans. */
    public const DIRECTORY = 'booking-references';

    public function store(StoreBookingUploadRequest $request): JsonResponse
    {
        $file = $request->file('file');

        // Laravel generates the stored name — a 40-character random string —
        // so a hostile original filename never reaches the filesystem, and the
        // resulting URL is not guessable from the customer's name or the date.
        // That unguessability is doing real work here: the `public` disk means
        // anyone holding the URL can open the photo.
        $path = $file->store(self::DIRECTORY.'/'.now()->format('Y/m'), MediaStorage::diskName());

        return response()->json([
            'data' => [
                // What the booking payload should carry.
                'path' => $path,
                'url' => MediaStorage::url($path),
                // Kept only as a display label for the admin screen. It is the
                // customer's own filename and is never used to address the file.
                'filename' => $file->getClientOriginalName(),
            ],
        ], 201);
    }
}
