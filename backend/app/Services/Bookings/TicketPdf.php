<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\URL;

/**
 * A booking's ticket: a one-page PDF with the event, date, time, place, how
 * many people, the booking reference and a QR code.
 *
 * The QR code holds the admin's check-in address for this ticket, so scanning
 * it with any phone camera opens the booking, ready to check in, for whoever
 * is signed in to the dashboard. It's drawn as a table of cells rather than an
 * image: the production image has no GD, and a table needs no image library.
 */
class TicketPdf
{
    /** No 0/O or 1/I, as on gift cards, so a code read aloud can't be misheard. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Gives the booking a ticket code if it has none, and returns it. */
    public function codeFor(Booking $booking): string
    {
        if ($booking->ticket_code) {
            return $booking->ticket_code;
        }

        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (Booking::query()->where('ticket_code', $code)->exists());

        $booking->update(['ticket_code' => $code]);

        return $code;
    }

    public function checkInUrl(Booking $booking): string
    {
        return config('app.admin_url').'/check-in?code='.$this->codeFor($booking);
    }

    /** A link to download the ticket that works without signing in, for a year. */
    public function downloadUrl(Booking $booking): string
    {
        return URL::temporarySignedRoute('api.v1.bookings.ticket', now()->addYear(), ['booking' => $booking->id]);
    }

    public function filename(Booking $booking): string
    {
        return 'ticket-'.$booking->reference().'.pdf';
    }

    /** The PDF's bytes. */
    public function render(Booking $booking): string
    {
        $booking->loadMissing('workshopSession.workshopType');
        $session = $booking->workshopSession;

        return Pdf::loadView('pdf.ticket', [
            'booking' => $booking,
            'reference' => $booking->reference(),
            'event' => $session?->eventName(),
            'under' => $session?->title ? $session->workshopType?->name : null,
            'date' => $session?->scheduled_date?->format('l j F Y'),
            'slot' => $session?->scheduled_slot,
            'place' => $session?->location_notes,
            'people' => $booking->attendees(),
            'name' => $booking->details['name'] ?? null,
            'code' => $this->codeFor($booking),
            'qr' => $this->qrRows($this->checkInUrl($booking)),
        ])
            ->setPaper('a5', 'portrait')
            // Only the characters used: the whole DejaVu font made every
            // ticket ~700 KB, which is a lot to attach to an email.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /** @return list<list<bool>> each row of the QR code, true for a dark cell */
    private function qrRows(string $data): array
    {
        $matrix = (new QRCode(new QROptions(['eccLevel' => EccLevel::M, 'addQuietzone' => true, 'quietzoneSize' => 2])))
            ->addByteSegment($data)
            ->getQRMatrix();
        $size = $matrix->getSize();

        $rows = [];
        for ($y = 0; $y < $size; $y++) {
            $row = [];
            for ($x = 0; $x < $size; $x++) {
                $row[] = $matrix->check($x, $y);
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
