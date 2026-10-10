<x-mail::message>
# Thank you, {{ $greetingName }}

Your payment has come through and your order is confirmed.

**Order {{ $order->reference }}** — {{ $total }}

<x-mail::table>
| Item | Qty | |
|:-----|:---:|--:|
@foreach ($order->items as $item)
| {{ $item->product_name }}@if ($item->variant_label) <br><small>{{ $item->variant_label }}</small>@endif | {{ $item->quantity }} | {{ $order->currency === 'USD' ? '$' : 'GH₵' }}{{ number_format($item->unit_price * $item->quantity / 100, 2) }} |
@endforeach
</x-mail::table>

{{-- Every figure below is quoted from GOLD_COAST_TOKOTA.md. Do not adjust a
     timeframe here without changing the document first — §22.3. --}}
Orders are processed within **48 hours** of payment confirmation. We will be in
touch again as soon as yours is on its way.

@if ($order->shipping_address)
**Delivering to**
{{ $order->shipping_address['full_name'] ?? '' }}
{{ $order->shipping_address['line1'] ?? '' }}
{{ $order->shipping_address['city'] ?? '' }}{{ ($order->shipping_address['region'] ?? null) ? ', '.$order->shipping_address['region'] : '' }}
{{ $order->shipping_address['country'] ?? '' }}
@endif

Returns are accepted within **{{ $returnWindowDays }} days** of receiving your
order. Keep this email — the order reference above is all we need to help you.

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
