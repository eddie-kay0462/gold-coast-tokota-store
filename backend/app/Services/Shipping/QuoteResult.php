<?php

namespace App\Services\Shipping;

use App\Enums\ShippingZone;

/**
 * Every delivery option for one checkout, plus anything the customer should
 * know before paying.
 *
 * `landedCost` is an estimate of the duties the customer pays the courier on
 * arrival. It is shown and never charged: §8 makes import duties the
 * customer's own responsibility, so DHL ships DAP.
 */
readonly class QuoteResult
{
    /**
     * @param  list<QuoteOption>  $options
     * @param  array{amount: int, currency: string}|null  $landedCost
     */
    public function __construct(
        public ShippingZone $zone,
        public array $options,
        public ?array $landedCost = null,
    ) {}

    public function option(string $id): ?QuoteOption
    {
        foreach ($this->options as $option) {
            if ($option->id === $id) {
                return $option;
            }
        }

        return null;
    }

    /** @param  list<QuoteOption>  $options */
    public function withOptions(array $options): self
    {
        return new self($this->zone, $options, $this->landedCost);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'zone' => $this->zone->value,
            'options' => array_map(fn (QuoteOption $option) => $option->toArray(), $this->options),
            'landedCost' => $this->landedCost,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            zone: ShippingZone::from($data['zone']),
            options: array_map(fn (array $option) => QuoteOption::fromArray($option), $data['options']),
            landedCost: $data['landedCost'],
        );
    }
}
