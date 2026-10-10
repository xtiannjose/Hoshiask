{{--
    One home for the app's outline icons: 24px grid, one stroke width, currentColor.
    Usage: <x-ui.icon name="x" class="h-4 w-4" />. Extra attributes (data-*, x-show, ...) pass through.
    Size is always set by the caller through `class`.
--}}
@props(['name'])

@php
    $icons = [
        // Navigation
        'home' => '<path d="M3 12l9-8 9 8M5 10v10h14V10"/>',
        'ticket' => '<path d="M8 6h8m-8 4h8m-8 4h5M5 3h14a2 2 0 012 2v4a2 2 0 000 4v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 000-4V5a2 2 0 012-2z"/>',
        'users' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M16 3.13a4 4 0 010 7.75M23 21v-2a4 4 0 00-3-3.87M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 016.5 17H20M6.5 17A2.5 2.5 0 004 19.5V5a2 2 0 012-2h14v14M6.5 17H20"/>',
        'chart' => '<path d="M4 20V10m6 10V4m6 16v-7m6 7V7"/>',
        'plus' => '<path d="M12 5v14m7-7H5"/>',
        'user' => '<path d="M20 21a8 8 0 10-16 0M12 11a4 4 0 100-8 4 4 0 000 8z"/>',
        'building' => '<path d="M4 21h16M7 21V7l5-3 5 3v14M9 10h.01M9 13h.01M9 16h.01M15 10h.01M15 13h.01M15 16h.01"/>',
        'shuffle' => '<path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/>',
        'canned' => '<path d="M8 10h8M8 14h5m-9 7h16a2 2 0 002-2V7a2 2 0 00-2-2H7L3 9v10a2 2 0 002 2z"/>',
        'pulse' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'help' => '<path d="M9.09 9a3 3 0 015.83 1c0 2-3 2-3 4M12 17h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        // Actions
        'x' => '<path d="M6 18L18 6M6 6l12 12"/>',
        'trash' => '<path d="M3 6h18M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6m4-6v6"/>',
        'eye' => '<path d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7s-8.268-2.943-9.542-7Z"/><circle cx="12" cy="12" r="3.25"/>',
        'reply' => '<path d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/>',
        // Theme toggle
        'moon' => '<path d="M21 12.8A9 9 0 1111.2 3a7 7 0 109.8 9.8z"/>',
        'sun' => '<path d="M12 3v1m0 16v1m8.66-10h-1M4.34 12h-1m14.02 6.36-.7-.7M7.02 7.02l-.7-.7m12.02 0-.7.7M7.02 16.98l-.7.7M12 8a4 4 0 100 8 4 4 0 000-8z"/>',
    ];
@endphp
<svg {{ $attributes->merge(['fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'viewBox' => '0 0 24 24', 'aria-hidden' => 'true', 'focusable' => 'false']) }}>{!! $icons[$name] ?? '' !!}</svg>
