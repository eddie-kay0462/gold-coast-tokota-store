<?php

namespace App\Mail;

use App\Models\Booking;
use App\Notifications\NotificationMessage;
use App\Notifications\NotificationRecipient;
use App\Services\Bookings\TicketPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One Mailable for every transactional email, driven by the
 * NotificationMessage it is handed.
 *
 * A class per email would put the subject in one file and the body in another
 * for five near-identical messages. The message object already carries both,
 * so the Mailable's only job is to hand them to Laravel.
 */
class TransactionalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotificationMessage $message,
        public readonly NotificationRecipient $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->message->subject);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return array_map(
            fn (array $file) => Attachment::fromData(
                fn () => isset($file['ticket_booking_id'])
                    ? app(TicketPdf::class)->render(Booking::findOrFail($file['ticket_booking_id']))
                    : base64_decode($file['base64'] ?? ''),
                $file['name'],
            )->withMime($file['mime']),
            $this->message->attachments,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: $this->message->view,
            with: [
                ...$this->message->data,
                'recipient' => $this->recipient,
                'greetingName' => $this->recipient->firstName(),
            ],
        );
    }
}
