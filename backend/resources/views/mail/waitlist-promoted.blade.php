<x-mail::message>
# Good news, {{ $greetingName }}

A place has opened up and you are **off the waitlist**.

@if ($when)
<x-mail::panel>
**{{ $when->format('l j F Y') }}**
@if ($booking->workshopSession?->workshopType)
<br>{{ $booking->workshopSession->workshopType->name }}
@endif
</x-mail::panel>
@endif

We will follow up with everything you need before the day. If you can no longer
make it, let us know so we can offer the place to someone else.

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
