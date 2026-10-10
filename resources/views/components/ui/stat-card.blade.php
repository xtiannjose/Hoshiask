@props([
    'href' => null,
    'label' => '',
    'value' => null,
    'valueClass' => 'text-slate-900',
    'labelClass' => 'text-slate-500',
    'contentClass' => 'text-xs text-slate-500',
    'cardClass' => '',
])

@php
    $hasContent = trim((string) $slot) !== '';
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class([$cardClass, 'stat-card', 'block' => $href]) }}>
    <div @class(['flex items-center justify-between' => isset($icon)])>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider {{ $labelClass }}">{{ $label }}</p>
            <p class="mt-2 font-display text-3xl font-semibold {{ $valueClass }}">{{ $value }}</p>
            @if($hasContent)
                <div class="mt-2 {{ $contentClass }}">{{ $slot }}</div>
            @endif
        </div>
        @isset($icon)
            {{ $icon }}
        @endisset
    </div>
</{{ $tag }}>
