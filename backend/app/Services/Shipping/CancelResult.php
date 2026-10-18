<?php

namespace App\Services\Shipping;

/** Whether a carrier booking was called off, and whether that cost anything. */
readonly class CancelResult
{
    /** @param  array<string, mixed>|null  $payload */
    public function __construct(
        public bool $cancelled,
        public bool $charged = false,
        public ?string $carrierStatus = null,
        public ?string $message = null,
        public ?array $payload = null,
    ) {}
}
