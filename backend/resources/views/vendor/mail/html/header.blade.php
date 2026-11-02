@props(['url'])
{{-- The storefront's announcement band, then the main logo on white. The logo
     travels inside the email as an inline image (`cid:logo`, attached by
     TransactionalMail), so it shows without loading anything from a server. --}}
<tr>
<td class="band">HANDCRAFTED IN GHANA</td>
</tr>
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="cid:logo" class="logo" width="174" height="43" alt="Gold Coast Tokota">
</a>
</td>
</tr>
