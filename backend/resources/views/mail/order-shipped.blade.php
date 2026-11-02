<x-mail::message>
# Your order is on its way, {{ $greetingName }}

Order **{{ $order->reference }}** has left our workshop with {{ $courier }}.

@if ($trackingUrl)
<x-mail::button :url="$trackingUrl">
Track your delivery
</x-mail::button>
@endif

@if ($tracking)
<x-mail::panel>
Tracking number: **{{ $tracking }}**
</x-mail::panel>
@elseif (! $trackingUrl)
{{-- The brand document promises tracking "where available", and a courier
     booked by hand may not give one. Saying nothing is better than showing an
     empty tracking box. --}}
We’ll send you a tracking number as soon as the courier gives us one.
@endif

@if ($international)
Delivery usually takes **{{ $deliveryEstimate }}**. Any customs duties, taxes or
import fees your country charges are paid on arrival.
@else
Delivery usually takes **{{ $deliveryEstimate }}**, depending on where you are.
@endif
</x-mail::message>
