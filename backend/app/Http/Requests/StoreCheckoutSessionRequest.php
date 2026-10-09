<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The request body `CheckoutPaymentStep` is written against:
 * `{ items, currency, shipping_address, delivery_method }`.
 *
 * Note what is absent: prices. The client sends an inventory item and a
 * quantity, and the server decides what that costs. A checkout that accepts a
 * posted price is a checkout that can be told what to charge.
 *
 * Each line names its stock row one of two ways: `inventory_item_id`, or
 * `slug` + `size` (+ `colour`, required when the style comes in more than one
 * colourway in that size). The storefront cart uses the second — it keys lines by
 * product and size, never held real row ids, and carts already sitting in
 * customers' cookies must still check out. Either way the controller only
 * ever sees resolved ids, via `checkoutItems()`.
 */
class StoreCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Guest checkout is supported by design (README Feature 4) — no forced
        // account creation. A logged-in customer is attached in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required_without:items.*.slug', 'nullable', 'integer', 'exists:inventory_items,id'],
            'items.*.slug' => ['required_without:items.*.inventory_item_id', 'nullable', 'string', 'max:255'],
            'items.*.size' => ['required_with:items.*.slug', 'nullable', 'string', 'max:16'],
            'items.*.colour' => ['nullable', 'string', 'max:40'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],

            'currency' => ['required', Rule::in(['GHS', 'USD'])],
            'delivery_method' => ['required', Rule::in(['standard', 'express'])],

            'shipping_address' => ['required', 'array'],
            'shipping_address.full_name' => ['required', 'string', 'max:255'],
            'shipping_address.email' => ['required', 'email', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:32'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.region' => ['nullable', 'string', 'max:120'],
            'shipping_address.postcode' => ['nullable', 'string', 'max:32'],
            // Required, and not merely nullable: country is what routes the
            // order to Yango or DHL, so an absent one is a validation error
            // before payment rather than an unpriced order (Feature 5 edge case).
            'shipping_address.country' => ['required', 'string', 'size:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_address.country.required' => 'A destination country is needed to work out delivery.',
            'items.required' => 'There is nothing in this order.',
        ];
    }

    /** @var array<int, int> request line index => inventory_items.id */
    private array $resolvedIds = [];

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->input('items', []) as $index => $line) {
                if (! empty($line['inventory_item_id'])) {
                    $this->resolvedIds[$index] = (int) $line['inventory_item_id'];

                    continue;
                }

                // Inactive products resolve too: the service turns those into
                // a 409 "no longer available", which tells the shopper more
                // than a validation error would.
                $product = Product::query()->where('slug', $line['slug'])->first(['id', 'name']);

                if ($product === null) {
                    $validator->errors()->add("items.{$index}.size", 'This product could not be found.');

                    continue;
                }

                [$itemId, $problem] = $this->resolveVariant($product->id, (string) $line['size'], $line['colour'] ?? null);

                if ($itemId === null) {
                    $validator->errors()->add($problem[0] === 'colour' ? "items.{$index}.colour" : "items.{$index}.size", $problem[1]);

                    continue;
                }

                $this->resolvedIds[$index] = (int) $itemId;
            }
        });
    }

    /**
     * The stock row for one size (and colour) of a product.
     *
     * Colour is matched case-insensitively — the cart stores the swatch name
     * as displayed. Rows without a colour belong to a style with no colour
     * axis, so a colour sent for one of those is ignored rather than refused.
     *
     * @return array{0: int|null, 1: array{0: string, 1: string}|null}
     */
    private function resolveVariant(int $productId, string $size, ?string $colour): array
    {
        $rows = InventoryItem::query()
            ->where('product_id', $productId)
            ->where('variant_attributes->size', $size)
            ->get(['id', 'variant_attributes']);

        if ($rows->isEmpty()) {
            return [null, ['size', "Size {$size} isn't made in this style."]];
        }

        $coloured = $rows->filter(fn (InventoryItem $row) => ! empty($row->variant_attributes['colour']));

        if ($coloured->isEmpty()) {
            return [$rows->first()->id, null];
        }

        if ($colour === null || trim($colour) === '') {
            return $coloured->count() === 1
                ? [$coloured->first()->id, null]
                : [null, ['colour', 'Choose a colour for this item.']];
        }

        $match = $coloured->first(
            fn (InventoryItem $row) => mb_strtolower($row->variant_attributes['colour']) === mb_strtolower(trim($colour)),
        );

        return $match
            ? [$match->id, null]
            : [null, ['colour', "This style isn't made in {$colour} in size {$size}."]];
    }

    /**
     * Validated lines with every one resolved to a stock row.
     *
     * @return array<int, array{inventory_item_id: int, quantity: int}>
     */
    public function checkoutItems(): array
    {
        return collect($this->validated('items'))
            ->map(fn (array $line, int $index) => [
                'inventory_item_id' => $this->resolvedIds[$index],
                'quantity' => (int) $line['quantity'],
            ])
            ->values()
            ->all();
    }
}
