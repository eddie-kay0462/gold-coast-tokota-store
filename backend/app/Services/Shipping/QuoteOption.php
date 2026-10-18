<?php

namespace App\Services\Shipping;

/**
 * One way to deliver an order, at one price.
 *
 * `amountGhs` is what the customer is charged, in GHS minor units. `rawAmount`
 * and `rawCurrency` are what the carrier actually said, before buffer or
 * threshold, kept so admin can compare the quote with the final bill.
 */
readonly class QuoteOption
{
    public const SOURCE_LIVE = 'live';         // priced by the carrier just now

    public const SOURCE_FALLBACK = 'fallback'; // carrier could not quote; configured rate

    public const SOURCE_FLAT = 'flat';         // the business's own rate by design

    public function __construct(
        /** Stable across re-quotes: "{carrier}:{service}". */
        public string $id,
        public string $carrier,
        public ?string $serviceCode,
        public string $label,
        public int $amountGhs,
        public string $source,
        public ?int $rawAmount = null,
        public ?string $rawCurrency = null,
        public int $bufferPercent = 0,
        public bool $free = false,
        public ?string $eta = null,
    ) {}

    public static function idFor(string $carrier, ?string $serviceCode): string
    {
        return $carrier.':'.($serviceCode ?? 'standard');
    }

    public function with(mixed ...$changes): self
    {
        return new self(...[...$this->toArray(), ...$changes]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'carrier' => $this->carrier,
            'serviceCode' => $this->serviceCode,
            'label' => $this->label,
            'amountGhs' => $this->amountGhs,
            'source' => $this->source,
            'rawAmount' => $this->rawAmount,
            'rawCurrency' => $this->rawCurrency,
            'bufferPercent' => $this->bufferPercent,
            'free' => $this->free,
            'eta' => $this->eta,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }

    /** The shape snapshotted onto orders.shipping_option. */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'carrier' => $this->carrier,
            'service_code' => $this->serviceCode,
            'label' => $this->label,
            'amount_ghs' => $this->amountGhs,
            'source' => $this->source,
            'quoted_raw' => $this->rawAmount,
            'quoted_currency' => $this->rawCurrency,
            'buffer_percent' => $this->bufferPercent,
            'free' => $this->free,
        ];
    }
}
