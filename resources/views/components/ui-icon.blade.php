@props(['name' => 'server', 'size' => 16])
<svg {{ $attributes->class(['ui-icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('link') <path d="m10 13 4-4M8 16l-2 2a4 4 0 0 1-6-6l5-5a4 4 0 0 1 6 0M16 8l2-2a4 4 0 0 1 6 6l-5 5a4 4 0 0 1-6 0" transform="translate(1 0) scale(.9)"/> @break
        @case('refresh') <path d="M20 7v5h-5M4 17v-5h5M5.6 7a8 8 0 0 1 13.2-2L20 7M4 17l1.2 2A8 8 0 0 0 18.4 17"/> @break
        @case('activity') <path d="M2 12h5l3-9 4 18 3-9h5"/> @break
        @case('chevron') <path d="m8 5 7 7-7 7"/> @break
        @case('settings') <path d="m9 3-1 3-3 1-2 3 2 2-1 3 2 3 3-1 2 3h3l1-3 3-1 2-3-2-2 1-3-2-3-3 1-2-3Z"/><circle cx="11.5" cy="11.5" r="3"/> @break
        @case('wrench') <path d="M14 6a5 5 0 0 0-6 6l-5 5a2.1 2.1 0 0 0 3 3l5-5a5 5 0 0 0 7-6l-3 3-3-3 3-3Z"/> @break
        @case('server')
            <rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>
            @break
        @case('pencil')
            <path d="m16 3 5 5M4 15 16 3a2 2 0 0 1 5 5L9 20l-6 1Z"/>
            @break
        @case('plus') <path d="M12 5v14M5 12h14"/> @break
        @case('check') <path d="m5 12 4 4L19 6"/> @break
        @case('x') <path d="m6 6 12 12M6 18 18 6"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5h4"/> @break
        @case('list') <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/> @break
        @case('search') <circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/> @break
        @case('trash') <path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/> @break
        @case('info') <circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/> @break
        @case('building') <path d="m3 8 9-5 9 5H3ZM5 8v12M10 8v12M14 8v12M19 8v12M3 21h18"/> @break
        @case('shield') <path d="m12 3 8 4v5c0 5-8 9-8 9s-8-4-8-9V7Z"/> @break
        @case('network') <rect x="9" y="3" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="5" rx="1"/><rect x="16" y="16" width="6" height="5" rx="1"/><path d="M12 9v4M5 16v-3h14v3"/> @break
        @case('bug') <rect x="7" y="7" width="10" height="14" rx="5"/><path d="M9 3l2 4m4-4-2 4M3 9l4 2m10 0 4-2M3 15h4m10 0h4M3 21l4-3m10 0 4 3M12 8v12"/> @break
        @case('pickaxe') <path d="m4 20 10-10M3 7c6-5 10-3 14 1s6 8 1 13c1-6-2-10-5-13S7 5 3 7Z"/> @break
        @case('alert') <path d="m12 3 10 18H2L12 3ZM12 9v4M12 17h.01"/> @break
        @case('lock') <rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/> @break
        @case('dot') <circle cx="12" cy="12" r="5" fill="currentColor" stroke="none"/> @break
    @endswitch
</svg>
