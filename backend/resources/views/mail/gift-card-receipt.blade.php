<x-mail::message>
# Thank you, {{ $greetingName }}

Your payment has come through.

**Order {{ $order->reference }}**: a {{ $amount }} gift card, {{ $total }} paid.

@if ($recipientEmail)
We have emailed the gift card to {{ $recipientName ?: $recipientEmail }} at {{ $recipientEmail }}.
@endif

Keep this email. The order reference above is all we need if anything goes wrong.

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
