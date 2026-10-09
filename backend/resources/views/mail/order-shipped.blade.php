<x-mail::message>
# Your order is on its way, {{ $greetingName }}

**Order {{ $order->reference }}** has been dispatched with {{ $courier }}.

@if ($tracking)
<x-mail::panel>
Tracking reference: **{{ $tracking }}**
</x-mail::panel>
@else
{{-- The brand document promises tracking "where available", and until courier
     credentials exist most orders will not have a reference. Saying nothing is
     better than showing an empty tracking box. --}}
We will pass on a tracking reference as soon as the courier provides one.
@endif

Standard domestic delivery takes **1–2 business days**, depending on
destination.

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
