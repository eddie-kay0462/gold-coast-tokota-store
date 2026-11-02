<x-mail::message>
@if ($storeCredit)
# Your store credit, {{ $greetingName }}

You have **{{ $balance }}** to spend at Gold Coast Tokota.
@else
# A gift for you, {{ $greetingName }}

@if ($card->sender_name)
{{ $card->sender_name }} has sent you a Gold Coast Tokota gift card worth **{{ $balance }}**.
@else
You’ve been sent a Gold Coast Tokota gift card worth **{{ $balance }}**.
@endif
@endif

@if ($card->message)
<x-mail::panel>
“{{ $card->message }}”@if ($card->sender_name)<br>{{ $card->sender_name }}@endif
</x-mail::panel>
@endif

Use this code at checkout:

<x-mail::panel>
<span style="color: #000000; font-size: 20px; font-weight: 500; letter-spacing: 2px;">{{ $card->code }}</span>
</x-mail::panel>

@if ($card->expires_at)
It can be used until **{{ $card->expires_at->format('j F Y') }}**.
@endif

<x-mail::button :url="$shopUrl">
Choose your pair
</x-mail::button>

Anyone with this code can spend it, so keep it somewhere safe.
</x-mail::message>
