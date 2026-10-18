<x-mail::message>
# Your order is on its way, {{ $greetingName }}

**Order {{ $order->reference }}** has been dispatched with {{ $courier }}.

@if ($trackingUrl)
<x-mail::button :url="$trackingUrl">
Track your delivery
</x-mail::button>
@endif

@if ($tracking)
<x-mail::panel>
Tracking reference: **{{ $tracking }}**
</x-mail::panel>
@elseif (! $trackingUrl)
{{-- The brand document promises tracking "where available", and a courier
     booked by hand may not give one. Saying nothing is better than showing an
     empty tracking box. --}}
We will pass on a tracking reference as soon as the courier provides one.
@endif

@if ($international)
Estimated delivery is **{{ $deliveryEstimate }}**. Any customs duties, taxes or
import fees charged by your country are paid on arrival.
@else
Standard domestic delivery takes **{{ $deliveryEstimate }}**, depending on
destination.
@endif

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
