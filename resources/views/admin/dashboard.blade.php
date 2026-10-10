@extends('layouts.app')

@section('title', ($dashboardTitle ?? 'Support Dashboard') . ' - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8"
    data-admin-dashboard-page
    data-heartbeat-url="{{ route('admin.dashboard', absolute: false) }}"
    data-snapshot-token="{{ $liveSnapshotToken ?? '' }}">
    @php
        $totalTicketsUrl = route('admin.tickets.index', ['tab' => 'all']);
        $openTicketsUrl = route('admin.tickets.index', ['tab' => 'tickets']);
        $severityOneTicketsUrl = route('admin.tickets.index', ['tab' => 'tickets', 'priority' => 'severity_1']);
    @endphp

    <h1 class="sr-only">{{ $dashboardTitle ?? 'Support Dashboard' }}</h1>

    <div class="panel mb-8 px-5 py-5 sm:px-6">
        <h2 class="font-display text-lg font-semibold text-slate-900">Quick Actions</h2>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('admin.tickets.index', ['tab' => 'all']) }}" class="btn-primary">View All Tickets</a>
            @if(!$isTechnical)
                <a href="{{ route('admin.tickets.index', ['tab' => 'attention']) }}" class="btn-warning">Needs Attention</a>
                <a href="{{ $severityOneTicketsUrl }}" class="btn-danger">Severity 1 Tickets</a>
            @else
                <a href="{{ $openTicketsUrl }}" class="btn-danger">Currently Open Tickets</a>
            @endif
            @if(!$isTechnical)
                <a href="{{ route('admin.reports.index') }}" class="btn-secondary">Reports</a>
            @endif
            @if(!$isTechnical)
                <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Manage Users</a>
            @endif
        </div>
    </div>

    <div class="stagger-fade mb-8 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card :href="$totalTicketsUrl" label="Total Tickets" :value="$stats['total_tickets']" aria-label="View total tickets">
            <x-slot:icon>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                    </svg>
                </span>
            </x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card :href="$openTicketsUrl" label="Open Tickets" :value="$stats['open_tickets']" aria-label="View open tickets">
            <x-slot:icon>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z" />
                    </svg>
                </span>
            </x-slot:icon>
        </x-ui.stat-card>

        @if(!$isTechnical)
            <x-ui.stat-card :href="route('admin.tickets.index', ['tab' => 'attention'])" label="Needs Attention" :value="$stats['attention_tickets']" value-class="text-amber-600" aria-label="View tickets that need attention">
                <x-slot:icon>
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 2.7 17.5A1.5 1.5 0 0 0 4 19.8h16a1.5 1.5 0 0 0 1.3-2.3L13.7 3.9a1.5 1.5 0 0 0-2.6 0Z" />
                        </svg>
                    </span>
                </x-slot:icon>
            </x-ui.stat-card>
        @endif

        <x-ui.stat-card :href="$severityOneTicketsUrl" label="Severity 1 Tickets" :value="$stats['severity_one_tickets']" value-class="text-red-600" aria-label="View severity 1 tickets">
            <x-slot:icon>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M8.25 6.75h7.5m-7.5 10.5h7.5M4.5 12A7.5 7.5 0 1 0 19.5 12a7.5 7.5 0 0 0-15 0Z" />
                    </svg>
                </span>
            </x-slot:icon>
        </x-ui.stat-card>
    </div>

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <div class="panel overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Recent Tickets</h2>
                    <p class="mt-1 text-sm text-slate-500">Latest support requests from users.</p>
                </div>
                <div class="max-h-[525px] overflow-y-auto">
                    <ul class="divide-y divide-slate-100">
                        @forelse($recentTickets as $ticket)
                            <li>
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="dashboard-ticket-link block px-5 py-4 transition sm:px-6">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ticket->status_color }}">
                                                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                                </span>
                                                <span class="text-xs text-slate-500">{{ $ticket->ticket_number }}</span>
                                            </div>
                                            <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $ticket->subject }}</p>
                                            <p class="mt-1 truncate text-sm text-slate-500">{{ $ticket->user->name }} - {{ $ticket->category->name }}</p>
                                        </div>
                                        <div class="flex items-center gap-2 self-start sm:self-auto">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ticket->priority_color }}">
                                                {{ $ticket->priority_label }}
                                            </span>
                                            <span class="text-xs text-slate-500">{{ $ticket->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        @empty
                            <li class="px-5 py-12 text-center sm:px-6">
                                <img src="{{ asset('images/brand/empty-tickets.png') }}" alt="" class="mx-auto mb-4 h-28 w-auto rounded-2xl">
                                <h3 class="text-sm font-semibold text-slate-900">No tickets yet</h3>
                                <p class="mt-1 text-sm text-slate-500">No support tickets have been created.</p>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Tickets by Status</h2>
                    <p class="mt-1 text-xs text-slate-500">Current month</p>
                </div>
                <div class="space-y-3 px-5 py-4">
                    @foreach(['open', 'in_progress', 'pending', 'resolved', 'closed', 'cancelled'] as $status)
                        @php
                            $statusFilter = match ($status) {
                                'resolved' => ['tab' => 'history'],
                                'closed' => ['tab' => 'history', 'status' => 'closed'],
                                'cancelled' => ['tab' => 'history', 'status' => 'cancelled'],
                                default => ['tab' => 'tickets', 'status' => $status],
                            };
                        @endphp
                        <a href="{{ route('admin.tickets.index', $statusFilter) }}" class="dashboard-summary-link group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 transition hover:bg-slate-100">
                            <span class="text-sm text-slate-600 transition group-hover:text-slate-700">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                            <span class="text-sm font-semibold text-slate-900">{{ ($ticketsByStatusDisplay ?? $ticketsByStatus)->get($status, 0) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Tickets by Severity</h2>
                    <p class="mt-1 text-xs text-slate-500">Current month</p>
                </div>
                <div class="space-y-3 px-5 py-4">
                    @foreach([null, ...\App\Enums\TicketPriority::cases()] as $priority)
                        @php
                            $priorityLinkParams = ['tab' => 'tickets'];
                            if ($priority !== null) {
                                $priorityLinkParams['priority'] = $priority->value;
                            }
                        @endphp
                        <a href="{{ route('admin.tickets.index', $priorityLinkParams) }}" class="dashboard-summary-link group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 transition hover:bg-slate-100">
                            <span class="text-sm text-slate-600 transition group-hover:text-slate-700">{{ $priority?->label() ?? \App\Enums\TicketPriority::SEVERITY_NOT_SET_LABEL }}</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $ticketsByPriority->get($priority?->value ?? '__pending__', 0) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-8 xl:grid-cols-3">
        <div class="panel overflow-hidden xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                <h2 class="font-display text-xl font-semibold text-slate-900">Ticket Volume (Last 30 Days)</h2>
                <p class="mt-1 text-sm text-slate-500">Tickets created per day, scoped to what you can see.</p>
            </div>
            <div class="px-5 py-6 sm:px-6">
                @php
                    $trendMax = max(1, collect($ticketsTrend)->max('count'));
                @endphp
                <div class="flex h-40 items-end gap-1" role="img" aria-label="Ticket volume for the last 30 days">
                    @foreach($ticketsTrend as $trendDay)
                        @php
                            $barHeightPercent = max(4, (int) round(($trendDay['count'] / $trendMax) * 100));
                        @endphp
                        <div class="group relative flex h-full flex-1 flex-col justify-end">
                            <div class="h-[{{ $barHeightPercent }}%] rounded-t bg-hoshishi-primary/70 transition group-hover:bg-hoshishi-primary"></div>
                            <div class="pointer-events-none absolute -top-7 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-xs text-white group-hover:block">
                                {{ \Illuminate\Support\Carbon::parse($trendDay['date'])->format('M j') }}: {{ $trendDay['count'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex justify-between text-xs text-slate-500">
                    <span>{{ \Illuminate\Support\Carbon::parse($ticketsTrend[0]['date'])->format('M j') }}</span>
                    <span>{{ \Illuminate\Support\Carbon::parse(end($ticketsTrend)['date'])->format('M j') }}</span>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-display text-lg font-semibold text-slate-900">Top Issues</h2>
                <p class="mt-1 text-xs text-slate-500">Most common categories this month</p>
            </div>
            <div class="space-y-3 px-5 py-4">
                @forelse($topIssues as $issue)
                    <a href="{{ route('admin.tickets.index', ['tab' => 'all', 'category' => $issue->category_id]) }}" class="dashboard-summary-link group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 transition hover:bg-slate-100">
                        <span class="truncate text-sm text-slate-600 transition group-hover:text-slate-700">{{ $issue->category_name }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ $issue->ticket_count }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">No ticket activity recorded yet this month.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
