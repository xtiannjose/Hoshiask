@extends('layouts.app')

@section('title', 'My Tickets - ' . config('app.name'))

@section('content')
@php
    $activeTab = $activeTab ?? 'tickets';
    $selectedStatus = $selectedStatus ?? 'all';
    $isHistoryTab = $activeTab === 'history';
    $tabQuery = request()->except(['tab', 'page']);
    $ticketsTabUrl = route('client.tickets.index', array_merge($tabQuery, ['tab' => 'tickets']));
    $historyTabUrl = route('client.tickets.index', array_merge($tabQuery, ['tab' => 'history']));
    $clearUrl = route('client.tickets.index', ['tab' => $activeTab]);
    $panelTitle = $isHistoryTab ? 'History' : 'Tickets';
    $panelDescription = $isHistoryTab ? 'Closed and resolved tickets.' : 'Open and active support requests.';
@endphp
<div
    class="mx-auto max-w-[1460px]"
    data-client-tickets-index-page
    data-route-base="{{ route('client.tickets.index', absolute: false) }}"
    data-snapshot-token="{{ $liveSnapshotToken ?? '' }}"
>
    <div class="mb-6">
        <h1 class="font-display text-4xl font-semibold text-slate-900">My Tickets</h1>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 pt-4 sm:px-6">
            <div class="flex items-center gap-6 border-b border-slate-200 pb-3">
                <a href="{{ $ticketsTabUrl }}" class="border-b-[3px] pb-3 text-sm font-semibold {{ $isHistoryTab ? 'border-transparent text-slate-500 hover:text-slate-800' : 'border-hoshishi-primary text-slate-900' }}">
                    Tickets
                </a>
                <a href="{{ $historyTabUrl }}" class="border-b-[3px] pb-3 text-sm font-semibold {{ $isHistoryTab ? 'border-hoshishi-primary text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
                    History
                </a>
            </div>

            <div class="pt-3">
                <h2 class="text-sm font-semibold text-slate-900">{{ $panelTitle }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $panelDescription }}</p>
            </div>

            <form method="GET" class="grid grid-cols-1 gap-3 py-4 md:grid-cols-3" data-submit-feedback data-search-history-form data-search-history-key="client-ticket-filters">
                <input type="hidden" name="tab" value="{{ $activeTab }}">

                <div class="relative md:col-span-2">
                    <label for="search" class="sr-only">Search</label>
                    <input
                        id="search"
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        data-search-history-input
                        class="h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-700 placeholder-slate-500 focus:border-hoshishi-primary focus:outline-none focus:ring-2 focus:ring-hoshishi-primary/20"
                        placeholder="Search tickets"
                        autocomplete="off"
                    >
                    <div class="search-history-panel hidden" data-search-history-panel></div>
                </div>

                <div>
                    <label for="status" class="sr-only">Status</label>
                    <select id="status" name="status" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-hoshishi-primary focus:outline-none focus:ring-2 focus:ring-hoshishi-primary/20">
                        @if($isHistoryTab)
                            <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>All history statuses</option>
                            <option value="resolved" {{ $selectedStatus === 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="closed" {{ $selectedStatus === 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="cancelled" {{ $selectedStatus === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        @else
                            <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>All statuses</option>
                            <option value="open" {{ in_array($selectedStatus, ['open', 'open_group'], true) ? 'selected' : '' }}>Open</option>
                            <option value="in_progress" {{ $selectedStatus === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="pending" {{ $selectedStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                        @endif
                    </select>
                </div>

                <div class="flex items-center gap-2 md:col-span-3">
                    <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-hoshishi-dark px-3 text-sm font-semibold text-white transition hover:brightness-95" data-loading-text="Filtering...">Filter</button>
                    <a href="{{ $clearUrl }}" class="inline-flex h-10 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Clear</a>
                </div>
            </form>
        </div>

        @include('client.tickets.partials.results')
    </section>
</div>
@endsection
