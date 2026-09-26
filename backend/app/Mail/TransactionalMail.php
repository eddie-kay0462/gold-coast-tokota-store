<?php

namespace App\Mail;

use App\Notifications\NotificationMessage;
use App\Notifications\NotificationRecipient;
use Illuminate\Bus\Queueable;
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
