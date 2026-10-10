<?php

namespace App\Jobs;

use App\Http\Controllers\Api\V1\BookingUploadController;
use App\Models\Booking;
use App\Support\MediaStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Deletes reference photos that never became a booking.
 *
 * `POST /booking-uploads` is unauthenticated — it has to be, because guest
 * bookings are supported — so without this, anyone can grow the disk without
 * limit, and every customer who opens the DIY form, picks a photo and changes
 * their mind leaves a file behind for good. The rate limit caps how fast that
 * happens; this is what stops it accumulating.
 *
 * **Age is checked before attachment**, so a file uploaded moments ago is
 * never deleted while the customer is still filling in the rest of the form.
 * A day is far longer than that gap and far shorter than "forever".
 */
class PruneOrphanedBookingUploads implements ShouldQueue
{
    use Queueable;

    /** How long an unattached upload is kept before it is considered abandoned. */
    public const GRACE_HOURS = 24;

    public function handle(): void
    {
        $disk = MediaStorage::privateDisk();
        $files = $disk->allFiles(BookingUploadController::DIRECTORY);

        if ($files === []) {
            return;
        }

        $attached = $this->attachedPaths();
        $cutoff = Carbon::now()->subHours(self::GRACE_HOURS)->getTimestamp();
        $deleted = 0;

        foreach ($files as $path) {
            if (in_array($path, $attached, true)) {
                continue;
            }

            if ($disk->lastModified($path) > $cutoff) {
                continue;
            }

            $disk->delete($path);
            $deleted++;
        }

        if ($deleted > 0) {
            Log::info('Pruned abandoned DIY reference photos.', ['deleted' => $deleted]);
        }
    }

    /**
     * Every reference photo currently claimed by a booking.
     *
     * Read in PHP rather than as a JSON query on purpose: production is
     * Postgres and the test suite is SQLite (issue 29), the two disagree about
     * JSON operators, and the booking table is small enough that portability is
     * worth more here than a smarter query. Revisit if bookings ever reach a
     * size where this matters.
     *
     * @return list<string>
     */
    private function attachedPaths(): array
    {
        return Booking::query()
            ->whereNotNull('details')
            ->pluck('details')
            ->map(fn ($details) => is_array($details) ? ($details['reference_image'] ?? null) : null)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values()
            ->all();
    }
}
