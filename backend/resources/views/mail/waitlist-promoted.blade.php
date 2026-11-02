<x-mail::message>
# A place has opened up, {{ $greetingName }}

@if ($payUrl)
It’s yours if you pay by **{{ $payBy?->format('l j F, g:ia') }}**. After that,
we’ll offer it to the next person on the waitlist.
@else
You’re off the waitlist, and your place is booked.
@endif

@if ($when)
<x-mail::panel>
@if ($event)
**{{ $event }}**<br>
@endif
{{ $when->format('l j F Y') }}
</x-mail::panel>
@endif

@if ($payUrl)
<x-mail::button :url="$payUrl">
Pay and keep my place
</x-mail::button>

Your ticket will arrive by email once you’ve paid.
@else
We’ll send you everything you need before the day. If you can no longer come,
please let us know so we can offer the place to someone else.
@endif
</x-mail::message>
