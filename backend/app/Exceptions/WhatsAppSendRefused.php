<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A message WhatsApp would refuse, or that the brand has promised not to
 * send, stopped before it is queued. Renders as a 422 in plain words, so the
 * person at the inbox learns why straight away instead of seeing a failed
 * tick a minute later.
 */
class WhatsAppSendRefused extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function windowClosed(): self
    {
        return new self(
            'This chat has been quiet for more than 24 hours, so WhatsApp only allows an approved template. Pick one from the template list.',
            'service_window_closed',
        );
    }

    public static function optedOut(): self
    {
        return new self(
            'This customer replied STOP, so templates and automatic messages are paused for them. They can still be answered once they write again.',
            'opted_out',
        );
    }

    public static function templateNotApproved(string $name): self
    {
        return new self("The template “{$name}” is not approved by WhatsApp yet, so it can’t be sent.", 'template_not_approved');
    }

    public static function notADraft(): self
    {
        return new self('That message has already been sent.', 'not_a_draft');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'reason' => $this->reason,
            'errors' => ['body' => [$this->getMessage()]],
        ], 422);
    }
}
