@php($productVersion = json_decode(file_get_contents(base_path('composer.json')), true)['version'] ?? null)
<small class="product-version">
    © {{ now()->year }} Trevizam Network
    @if ($productVersion)<br>Ver. {{ $productVersion }}@endif
</small>
