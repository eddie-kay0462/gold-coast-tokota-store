<?php

namespace App\Jobs;

use App\Notifications\NotificationMessage;
use App\Notifications\NotificationRecipient;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tells the team something needs a person. Plain strings rather than models,
 * so the job serialises the same however the message was built.
 */
class SendOpsAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly string $key,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?string $sms = null,
        public readonly ?string $link = null,
    ) {}

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $dispatcher->send(
            new NotificationRecipient(
                email: config('shipping.ops.email'),
                phone: config('shipping.ops.phone'),
                name: 'Team',
            ),
            new NotificationMessage(
                key: $this->key,
                subject: $this->subject,
                view: 'mail.ops-alert',
                data: ['body' => $this->body, 'link' => $this->link],
                sms: $this->sms,
            ),
        );
    }
}
