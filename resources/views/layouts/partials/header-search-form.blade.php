@php
    $wrapperClass = $wrapperClass ?? 'relative w-full';
@endphp
<form method="GET" action="{{ $headerSearchAction }}" @class([
    $wrapperClass,
    'mx-auto max-w-3xl' => $isClient,
    'mx-auto max-w-xl' => !$isClient,
]) data-search-history-form data-search-history-key="header-global">
    @foreach($headerSearchCarryParams as $paramKey => $paramValue)
        @if(is_array($paramValue))
            @foreach($paramValue as $arrayValue)
                <input type="hidden" name="{{ $paramKey }}[]" value="{{ $arrayValue }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $paramKey }}" value="{{ $paramValue }}">
        @endif
    @endforeach
    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"></path>
    </svg>
    <input
        type="text"
        name="search"
        value="{{ $headerSearchQuery }}"
        data-search-history-input
        class="h-12 w-full rounded-2xl border border-slate-300 bg-white pl-10 pr-4 text-sm text-slate-700 placeholder-slate-500 focus:border-hoshishi-primary focus:outline-none focus:ring-2 focus:ring-hoshishi-primary/20"
        placeholder="{{ $headerSearchPlaceholder }}"
        aria-label="{{ $headerSearchPlaceholder }}"
        autocomplete="off"
    >
    <div class="search-history-panel hidden" data-search-history-panel></div>
</form>
