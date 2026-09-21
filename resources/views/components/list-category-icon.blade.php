@props(['lista'])
@php
    // Presentation only: unrecognized/custom lists keep a neutral list icon.
    $label = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($lista->nome));
    $icon = match (true) {
        $lista->isAnatel(), str_contains($label, 'anatel') => 'building',
        str_contains($label, 'ransomware') => 'lock',
        str_contains($label, 'phishing'), str_contains($label, 'golpe') => 'alert',
        str_contains($label, 'botnet'), str_contains($label, 'controle remoto') => 'network',
        str_contains($label, 'mineracao'), str_contains($label, 'criptomoeda') => 'pickaxe',
        str_contains($label, 'malware'), str_contains($label, 'virus') => 'bug',
        str_contains($label, 'ameaca') => 'shield',
        default => 'list',
    };
@endphp
<x-ui-icon :name="$icon" {{ $attributes->class(['server-category-icon', 'server-category-'.$icon]) }} />
