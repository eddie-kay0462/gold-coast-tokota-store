<?php

namespace App\Services\WhatsApp;

/** What WhatsApp said to one send. */
final class SendResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $wamid,
        public readonly ?string $error,
        public readonly ?int $errorCode,
        /** True when trying again later could succeed (throttling, an outage). */
        public readonly bool $retryable,
    ) {}

    public static function sent(string $wamid): self
    {
        return new self(true, $wamid, null, null, false);
    }

    public static function failed(string $error, ?int $code = null, bool $retryable = false): self
    {
        return new self(false, null, $error, $code, $retryable);
    }
}
