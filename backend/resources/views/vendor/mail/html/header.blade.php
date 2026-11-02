@props(['url'])
{{-- The storefront's announcement band, then the main logo on white, loaded
     from `mail.logo_url` (MAIL_LOGO_URL). Not embedded: Gmail lists an
     embedded image as an attachment. --}}
<tr>
<td class="band">HANDCRAFTED IN GHANA</td>
</tr>
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ config('mail.logo_url') }}" class="logo" width="174" height="43" alt="Gold Coast Tokota">
</a>
</td>
</tr>
