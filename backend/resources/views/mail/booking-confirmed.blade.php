<x-mail::message>
# You are confirmed, {{ $greetingName }}

@if ($isDiy)
Your Design-Your-Own order is confirmed and in the queue.
@elseif ($paid)
Thank you for paying. Your place is confirmed, and **your ticket is attached**.
Show it at the door, on your phone or printed: one ticket covers everyone on the booking.
@else
Your place is confirmed, and **your ticket is attached**. Show it at the door,
on your phone or printed: one ticket covers everyone on the booking.
@endif

@if ($when)
<x-mail::panel>
**{{ $when->format('l j F Y') }}**@if ($slot) · {{ $slot }}@endif
@if ($event)
<br>{{ $event }}
@endif
@unless ($isDiy)
<br>{{ $people }} {{ $people === 1 ? 'person' : 'people' }} · booking {{ $booking->reference() }}
@endunless
</x-mail::panel>
@endif

@if ($ticketUrl)
<x-mail::button :url="$ticketUrl">
Open your ticket
</x-mail::button>
@endif

If anything changes on your side, reply to this email or reach us on WhatsApp
and we will sort it out.

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
