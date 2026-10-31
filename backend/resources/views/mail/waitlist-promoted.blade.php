<x-mail::message>
# Good news, {{ $greetingName }}

@if ($payUrl)
A place has opened up, and it's **yours if you pay by {{ $payBy?->format('l j F, g:ia') }}**.
After that it goes to the next person on the waitlist.
@else
A place has opened up and you are **off the waitlist**.
@endif

@if ($when)
<x-mail::panel>
**{{ $when->format('l j F Y') }}**
@if ($event)
<br>{{ $event }}
@endif
</x-mail::panel>
@endif

@if ($payUrl)
<x-mail::button :url="$payUrl">
Pay and keep my place
</x-mail::button>

Your ticket comes by email as soon as you've paid.
@else
We will follow up with everything you need before the day. If you can no longer
make it, let us know so we can offer the place to someone else.
@endif

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
