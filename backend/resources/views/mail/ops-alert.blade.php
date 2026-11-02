<x-mail::message>
# Needs attention

{{ $body }}

@if ($link)
<x-mail::button :url="$link">
Open the order
</x-mail::button>
@endif
</x-mail::message>
