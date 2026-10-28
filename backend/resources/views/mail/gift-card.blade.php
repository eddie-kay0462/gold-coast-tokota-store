<x-mail::message>
@if ($storeCredit)
# Your store credit, {{ $greetingName }}

You have **{{ $balance }}** of store credit to spend at Gold Coast Tokota.
@else
# A gift for you, {{ $greetingName }}

@if ($card->sender_name)
{{ $card->sender_name }} has sent you a Gold Coast Tokota gift card worth **{{ $balance }}**.
@else
You have been sent a Gold Coast Tokota gift card worth **{{ $balance }}**.
@endif
@endif

@if ($card->message)
<x-mail::panel>
{{ $card->message }}
</x-mail::panel>
@endif

Enter this code at checkout:

<x-mail::panel>
**{{ $card->code }}**
</x-mail::panel>

@if ($card->expires_at)
It can be used until **{{ $card->expires_at->format('j F Y') }}**.
@endif

<x-mail::button :url="$shopUrl">
Choose your pair
</x-mail::button>

Keep this email: the code is all you need, and anyone who has it can spend it.

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
