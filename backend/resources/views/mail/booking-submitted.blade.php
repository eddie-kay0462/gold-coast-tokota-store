<x-mail::message>
# Thank you, {{ $greetingName }}

@if ($isWaitlisted)
That session is fully booked, so we have added you to the **waitlist**. If a
place opens up we will contact you straight away — there is nothing else you
need to do.
@elseif ($isDiy)
We have your **Design-Your-Own** request and will be in touch to confirm the
details and your turnaround time.
@else
We have your booking request. It is not confirmed yet — we will email you again
once it is.
@endif

@if ($booking->workshopSession)
<x-mail::panel>
{{ $booking->workshopSession->workshopType?->name ?? 'Workshop' }}<br>
{{ $booking->workshopSession->scheduled_date?->format('l j F Y') }}
</x-mail::panel>
@endif

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
