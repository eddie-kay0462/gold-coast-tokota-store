<x-mail::message>
# You are confirmed, {{ $greetingName }}

@if ($isDiy)
Your Design-Your-Own order is confirmed and in the queue.
@else
Your place is confirmed. We look forward to seeing you.
@endif

@if ($when)
<x-mail::panel>
**{{ $when->format('l j F Y') }}**
@if ($booking->workshopSession?->workshopType)
<br>{{ $booking->workshopSession->workshopType->name }}
@endif
</x-mail::panel>
@endif

If anything changes on your side, reply to this email or reach us on WhatsApp
and we will sort it out.

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
