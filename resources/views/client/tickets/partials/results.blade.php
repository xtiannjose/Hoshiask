@php
    $isHistoryTab = ($activeTab ?? 'tickets') === 'history';
    $emptyTitle = $isHistoryTab ? 'No history tickets found' : 'No tickets found';
    $emptyDescription = $isHistoryTab
        ? 'Resolved and closed tickets will appear here.'
        : 'Try adjusting your filters.';
@endphp
<div data-client-tickets-results>
    <div class="space-y-3 px-4 pb-4 md:hidden">
        @forelse($tickets as $ticket)
            @php
                $createdLabel = $ticket->created_at->greaterThan(now()->subDay())
                    ? $ticket->created_at->diffForHumans()
                    : $ticket->created_at->format('M j, Y');
            @endphp
            <a href="{{ route('client.tickets.show', $ticket) }}" class="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="wrap-anywhere text-sm font-semibold text-slate-900">({{ $ticket->ticket_number }}) {{ $ticket->subject }}</p>
                <p class="mt-1 text-xs text-slate-600">{{ $ticket->category->name }}</p>
                <p class="mt-1 text-xs text-slate-500">Created {{ $createdLabel }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600">
                    <div>
                        <span class="block text-xs uppercase tracking-wide text-slate-500">Assigned</span>
                        <span>{{ $ticket->assigned_users_label }}</span>
                    </div>
                    <div>
                        <span class="block text-xs uppercase tracking-wide text-slate-500">Activity</span>
                        <span>{{ $ticket->activity_label }}</span>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <span class="inline-flex min-w-16 items-center justify-center whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-semibold uppercase tracking-wide {{ $ticket->status_badge_class }}">
                        {{ $ticket->status_label }}
                    </span>
                </div>
            </a>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-10 text-center">
                <p class="text-base font-semibold text-slate-700">{{ $emptyTitle }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $emptyDescription }}</p>
            </div>
        @endforelse
    </div>

    <div class="hidden max-h-[70vh] overflow-auto md:block">
        <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
            <thead class="sticky top-0 z-10 bg-[#fafbfc] text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="w-[44%] px-6 py-4">Details</th>
                    <th class="w-[20%] px-6 py-4">Assigned Staff</th>
                    <th class="w-[22%] px-6 py-4 text-center">Activity Status</th>
                    <th class="w-[14%] px-6 py-4 text-center">Status</th>
                </tr>
            </thead>

            <tbody class="app-table-body divide-y divide-slate-200 bg-white">
                @forelse($tickets as $ticket)
                    @php
                        $createdLabel = $ticket->created_at->greaterThan(now()->subDay())
                            ? $ticket->created_at->diffForHumans()
                            : $ticket->created_at->format('M j, Y');
                    @endphp

                    <tr class="client-ticket-row transition">
                        <td class="px-6 py-5 align-top">
                            <a href="{{ route('client.tickets.show', $ticket) }}" class="block">
                                <p class="truncate text-base font-semibold text-slate-900">({{ $ticket->ticket_number }}) {{ $ticket->subject }}</p>
                                <p class="mt-0.5 text-sm text-slate-600">{{ $ticket->category->name }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    Created {{ $createdLabel }}
                                </p>
                            </a>
                        </td>

                        <td class="px-6 py-5 align-top text-sm text-slate-700">
                            {{ $ticket->assigned_users_label }}
                        </td>

                        <td class="px-6 py-5 text-center align-top">
                            <span class="inline-flex items-center gap-2 text-sm text-slate-600">
                                <span class="h-2.5 w-2.5 rounded-full {{ $ticket->activity_dot_class }}"></span>
                                {{ $ticket->activity_label }}
                            </span>
                        </td>

                        <td class="px-6 py-5 text-center align-top">
                            <span class="inline-flex min-w-16 items-center justify-center whitespace-nowrap rounded-lg px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $ticket->status_badge_class }}">
                                {{ $ticket->status_label }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-14 text-center">
                            <p class="text-base font-semibold text-slate-700">{{ $emptyTitle }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $emptyDescription }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tickets->count() > 0)
        <div data-client-ticket-pagination class="border-t border-slate-200 px-6 py-4">
            {{ $tickets->appends(request()->except(['page', 'partial']))->links() }}
        </div>
    @endif
</div>
