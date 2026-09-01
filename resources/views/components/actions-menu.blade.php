@props(['label' => 'Ações'])

@php
    $menuId = 'actions-menu-' . \Illuminate\Support\Str::random(10);
@endphp

<div class="actions-menu">
    <button
        type="button"
        class="actions-menu-toggle"
        data-actions-menu-toggle
        aria-controls="{{ $menuId }}"
        aria-haspopup="true"
        aria-expanded="false"
        aria-label="{{ $label }}"
        title="{{ $label }}"
    >&#8942;</button>
    <div class="actions-menu-dropdown" id="{{ $menuId }}" data-actions-menu-dropdown hidden>
        {{ $slot }}
    </div>
</div>
