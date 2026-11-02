<x-mail::message>
# Thank you, {{ $greetingName }}

We’ve received your payment for a **{{ $amount }}** gift card.

<x-mail::panel>
**Order {{ $order->reference }}**<br>
{{ $total }} paid
</x-mail::panel>

@if ($recipientEmail)
The gift card is on its way to {{ $recipientName ?: $recipientEmail }} at {{ $recipientEmail }}.
@endif

Keep this email: your order number is all we need if you have a question.
</x-mail::message>
