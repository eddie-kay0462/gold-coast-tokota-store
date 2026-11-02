{{--
    The frame every email shares: the storefront's band and logo above, and a
    footer with the brand's channels below. The channels, address and email
    come from Site settings, so a change in admin reaches every email. A
    channel with no value set is left out rather than linked to nowhere.
--}}
@php
    $site = \App\Models\SiteSetting::current();
    $storefront = rtrim((string) config('app.storefront_url'), '/');
    $whatsapp = preg_replace('/\D/', '', (string) $site->whatsapp_number);
    $channels = array_filter([
        'Instagram' => $site->instagram_url,
        'WhatsApp' => $whatsapp ? "https://wa.me/{$whatsapp}" : null,
        'Shop online' => $storefront ?: null,
    ]);
@endphp
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$storefront">
Gold Coast Tokota
</x-mail::header>
</x-slot:header>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
<p class="channels">
@foreach ($channels as $label => $href)
<a href="{{ $href }}">{{ strtoupper($label) }}</a>@if (! $loop->last)<span class="dot">&nbsp;&nbsp;·&nbsp;&nbsp;</span>@endif
@endforeach
</p>
<p class="contact">
Handcrafted sandals and ahenema, made in Ghana.<br>
@if ($site->address){{ $site->address }}@endif
@if ($site->address && $site->contact_email)<br>@endif
@if ($site->contact_email)<a href="mailto:{{ $site->contact_email }}">{{ $site->contact_email }}</a>@endif
</p>
<p class="legal">© {{ date('Y') }} Gold Coast Tokota</p>
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
