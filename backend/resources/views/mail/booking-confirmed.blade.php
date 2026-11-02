<x-mail::message>
# You’re confirmed, {{ $greetingName }}

@if ($isDiy)
Your Design-Your-Own order is confirmed, and we’ve started on it.
@elseif ($paid)
Thank you for your payment. Your place is confirmed, and **your ticket is
attached**. Show it at the door, on your phone or printed. One ticket covers
everyone on the booking.
@else
Your place is confirmed, and **your ticket is attached**. Show it at the door,
on your phone or printed. One ticket covers everyone on the booking.
@endif

@if ($when)
<x-mail::panel>
@if ($event)
**{{ $event }}**<br>
@endif
{{ $when->format('l j F Y') }}@if ($slot) · {{ $slot }}@endif
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

If your plans change, reply to this email or message us on WhatsApp.
</x-mail::message>
