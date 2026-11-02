<x-mail::message>
# Thank you for your order, {{ $greetingName }}

We’ve received your payment, and your order is confirmed.

<x-mail::panel>
**Order {{ $order->reference }}**<br>
{{ $total }} paid
</x-mail::panel>

<x-mail::table>
| Item | Qty | |
|:-----|:---:|--:|
@foreach ($order->items as $item)
| {{ $item->product_name }}@if ($item->variant_label) <br><small>{{ $item->variant_label }}</small>@endif | {{ $item->quantity }} | {{ $order->currency === 'USD' ? '$' : 'GH₵' }}{{ number_format($item->unit_price * $item->quantity / 100, 2) }} |
@endforeach
</x-mail::table>

@if ($order->gift_card_amount > 0)
Paid with a gift card: **{{ $order->currency === 'USD' ? '$' : 'GH₵' }}{{ number_format($order->gift_card_amount / 100, 2) }}**
@endif

{{-- Every figure below is quoted from GOLD_COAST_TOKOTA.md. Do not adjust a
     timeframe here without changing the document first (§22.3). --}}
Each pair is prepared by hand, and orders are processed within **48 hours** of
payment. We’ll email you again when yours is on its way.

@if ($order->shipping_address)
**Delivering to**<br>
{{ $order->shipping_address['full_name'] ?? '' }}<br>
{{ $order->shipping_address['line1'] ?? '' }}<br>
{{ $order->shipping_address['city'] ?? '' }}{{ ($order->shipping_address['region'] ?? null) ? ', '.$order->shipping_address['region'] : '' }}<br>
@php($country = strtoupper((string) ($order->shipping_address['country'] ?? '')))
{{ $country && class_exists(\Locale::class) ? (\Locale::getDisplayRegion('-'.$country, 'en') ?: $country) : $country }}
@endif

**Track your order** at any time with your order number,
**{{ $order->reference }}**, and this email address.

<x-mail::button :url="$statusUrl">
Track your order
</x-mail::button>

You can return an item within **{{ $returnWindowDays }} days** of receiving it.
Keep this email: your order number is all we need if you have a question.
</x-mail::message>
