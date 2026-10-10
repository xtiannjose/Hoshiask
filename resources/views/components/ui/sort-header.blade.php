@props(['key', 'sort' => 'created', 'direction' => 'desc'])

{{-- A column heading that sorts the staff ticket list: click once for the natural order, again to flip it. --}}
@php
    $isActive = $sort === $key;
    $nextDirection = $isActive
        ? ($direction === 'asc' ? 'desc' : 'asc')
        : \App\Support\TicketListSort::defaultDirection($key);
    $href = route('admin.tickets.index', array_merge(
        request()->except(['page', 'partial', 'heartbeat', 'sort', 'direction']),
        ['sort' => $key, 'direction' => $nextDirection]
    ));
    $describe = $isActive
        ? 'sorted '.($direction === 'asc' ? 'ascending' : 'descending').', click to reverse'
        : 'click to sort';
@endphp
<a href="{{ $href }}" class="inline-flex items-center gap-1 rounded py-1 text-inherit hover:text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-hoshishi-primary" title="{{ trim(strip_tags((string) $slot)) }}: {{ $describe }}">
    <span>{{ $slot }}</span>
    <span aria-hidden="true" class="text-[0.7rem] leading-none {{ $isActive ? 'text-slate-800' : 'text-slate-300' }}">{{ $isActive ? ($direction === 'asc' ? '▲' : '▼') : '↕' }}</span>
</a>
