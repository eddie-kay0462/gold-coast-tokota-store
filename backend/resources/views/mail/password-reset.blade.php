<x-mail::message>
# Reset your password

Hello {{ $greetingName }}, we received a request to reset the password on your
Gold Coast Tokota account.

<x-mail::button :url="$url">
Reset password
</x-mail::button>

This link expires in **{{ $expiresInMinutes }} minutes**.

{{-- Said plainly rather than as a warning: the common case for this email is
     someone who did ask, and the rare case needs no action at all. --}}
If you did not ask for this, you can ignore this email — your password will not
change until the link above is used.

Thanks,<br>
{{ config('mail.from.name') }}
</x-mail::message>
