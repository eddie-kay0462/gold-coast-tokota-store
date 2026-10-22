<?php

namespace App\Listeners;

use App\Events\WhatsAppMessageReceived;
use App\Services\WhatsApp\AutoReplies;

/** The greeting and after-hours replies (see AutoReplies). Runs inside the queued webhook job. */
class SendWhatsAppAutoReply
{
    public function __construct(private readonly AutoReplies $replies) {}

    public function handle(WhatsAppMessageReceived $event): void
    {
        $this->replies->handle($event->message);
    }
}
