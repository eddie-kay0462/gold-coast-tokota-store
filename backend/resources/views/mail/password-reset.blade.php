<x-mail::message>
# Reset your password

Hello {{ $greetingName }}. We received a request to reset the password for your
Gold Coast Tokota account.

<x-mail::button :url="$url">
Reset password
</x-mail::button>

This link expires in **{{ $expiresInMinutes }} minutes**.

{{-- Said plainly rather than as a warning: the common case for this email is
     someone who did ask, and the rare case needs no action at all. --}}
If you didn’t ask for this, you can ignore this email. Your password won’t
change unless the link is used.
</x-mail::message>
