@php
    $site = \App\Models\SiteSetting::current();
    $storefront = rtrim((string) config('app.storefront_url'), '/');
    $whatsapp = preg_replace('/\D/', '', (string) $site->whatsapp_number);
@endphp
<x-mail::layout>
    <x-slot:header>
        <x-mail::header :url="$storefront">
            GOLD COAST TOKOTA
        </x-mail::header>
    </x-slot:header>

    {{ $slot }}

    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    <x-slot:footer>
        <x-mail::footer>
@if ($site->instagram_url)Instagram: {{ $site->instagram_url }}
@endif
@if ($whatsapp)WhatsApp: https://wa.me/{{ $whatsapp }}
@endif
@if ($storefront)Shop online: {{ $storefront }}
@endif

Handcrafted sandals and ahenema, made in Ghana.
@if ($site->address){{ $site->address }}
@endif
@if ($site->contact_email){{ $site->contact_email }}
@endif

© {{ date('Y') }} Gold Coast Tokota
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
