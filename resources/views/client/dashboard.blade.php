@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8"
    data-client-dashboard-page
    data-heartbeat-url="{{ route('client.dashboard', absolute: false) }}"
    data-snapshot-token="{{ $liveSnapshotToken ?? '' }}">
    @php
        $totalTicketsUrl = route('client.tickets.index');
        $openTicketsUrl = route('client.tickets.index', ['status' => 'open']);
        $inProgressTicketsUrl = route('client.tickets.index', ['status' => 'in_progress']);
    @endphp

    <div class="mb-6">
        <h1 class="font-display text-3xl font-semibold text-slate-900">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Overview of your ticket activity.</p>
    </div>

    <div class="stagger-fade mb-8 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        <x-ui.stat-card :href="$totalTicketsUrl" label="Total Tickets" :value="$stats['total_tickets']">
            <x-slot:icon>
                <div class="rounded-xl bg-hoshishi-blue-100 p-3 text-hoshishi-blue-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card :href="$openTicketsUrl" label="Open Tickets" :value="$stats['open_tickets']">
            <x-slot:icon>
                <div class="rounded-xl bg-emerald-100 p-3 text-emerald-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card :href="$inProgressTicketsUrl" label="In Progress" :value="$stats['in_progress_tickets']">
            <x-slot:icon>
                <div class="rounded-xl bg-sky-100 p-3 text-sky-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0Z" />
                    </svg>
                </div>
            </x-slot:icon>
        </x-ui.stat-card>

    </div>

    <div class="panel overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
            <h2 class="font-display text-xl font-semibold text-slate-900">Recent Tickets</h2>
            <p class="mt-1 text-sm text-slate-500">Your most recent support requests.</p>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($recentTickets as $ticket)
                <li>
                    <a href="{{ route('client.tickets.show', $ticket) }}" class="dashboard-ticket-link block px-5 py-4 transition sm:px-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ticket->status_color }}">
                                        {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                    </span>
                                    <span class="text-xs text-slate-500">{{ $ticket->ticket_number }}</span>
                                </div>
                                <p class="mt-2 truncate text-base font-semibold leading-6 text-slate-900 sm:text-lg">{{ $ticket->subject }}</p>
                                <p class="mt-1 truncate text-sm text-slate-500">{{ $ticket->category->name }}</p>
                            </div>
                            <div class="flex items-center gap-2 self-start sm:self-auto">
                                <span class="text-xs text-slate-500">{{ $ticket->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </a>
                </li>
            @empty
                <li class="px-5 py-12 text-center sm:px-6">
                    <img src="{{ asset('images/brand/empty-tickets.png') }}" alt="" class="mx-auto mb-4 h-28 w-auto rounded-2xl">
                    <h3 class="text-sm font-semibold text-slate-900">No tickets yet</h3>
                    <p class="mt-1 text-sm text-slate-500">Use the New Ticket button in the top bar to get started.</p>
                </li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
