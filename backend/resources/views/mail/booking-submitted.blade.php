<x-mail::message>
# Thank you, {{ $greetingName }}

@if ($isWaitlisted)
That session is full, so you’re on the **waitlist**. If a place opens up, we’ll
contact you.
@elseif ($isDiy)
We’ve received your **Design-Your-Own** request. We’ll be in touch to confirm the
details and when it will be ready.
@else
We’ve received your booking request. It isn’t confirmed yet; we’ll email you
again once it is.
@endif

@if ($booking->workshopSession)
<x-mail::panel>
**{{ $booking->workshopSession->eventName() ?? 'Workshop' }}**<br>
{{ $booking->workshopSession->scheduled_date?->format('l j F Y') }}@if ($booking->workshopSession->scheduled_slot) · {{ $booking->workshopSession->scheduled_slot }}@endif
</x-mail::panel>
@endif
</x-mail::message>
