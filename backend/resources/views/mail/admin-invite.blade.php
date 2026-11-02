<x-mail::message>
# You’re invited

Hello {{ $greetingName }}. {{ $invitedBy }} has added you to the Gold Coast
Tokota dashboard as **{{ $roleLabel }}**.

Choose a password to finish setting up your account. You’ll sign in with this
email address.

<x-mail::button :url="$url">
Set your password
</x-mail::button>

This link works for **{{ $expiresInDays }} days**. If it expires, ask
{{ $invitedBy }} to send a new one.
</x-mail::message>
