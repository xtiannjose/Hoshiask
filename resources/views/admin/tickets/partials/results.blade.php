@php
    $tab = $activeTab ?? 'tickets';
    $sort = $sort ?? 'created';
    $sortDirection = $sortDirection ?? 'desc';
    $perPage = $perPage ?? \App\Support\TicketListSort::DEFAULT_PER_PAGE;
    $isHistoryTab = $tab === 'history';
    $closedRevertWindowDays = 7;
    $canDeleteTickets = auth()->user()->isSuperAdmin();
    $paginationQuery = request()->except(['page', 'partial']);
    $resultStart = $tickets->firstItem();
    $resultEnd = $tickets->lastItem();
    $resultTotal = $tickets->total();
@endphp

<div data-admin-tickets-results>
    <div class="flex flex-wrap items-center justify-between gap-2 px-5 pb-3 text-sm text-slate-500 sm:px-6">
        @if($resultTotal > 0)
            <p>
                Showing <span class="font-semibold text-slate-700">{{ number_format($resultStart) }}-{{ number_format($resultEnd) }}</span>
                of <span class="font-semibold text-slate-700">{{ number_format($resultTotal) }}</span>
                {{ Str::plural('ticket', $resultTotal) }}
            </p>
        @else
            <p>No tickets match the current filters.</p>
        @endif

        <p class="flex items-center gap-1 text-xs" data-page-size>
            <span>Rows per page:</span>
            @foreach(\App\Support\TicketListSort::PER_PAGE_OPTIONS as $sizeOption)
                <a
                    href="{{ route('admin.tickets.index', array_merge(request()->except(['page', 'partial', 'heartbeat', 'per_page']), $sizeOption === \App\Support\TicketListSort::DEFAULT_PER_PAGE ? [] : ['per_page' => $sizeOption])) }}"
                    class="inline-flex min-w-8 items-center justify-center rounded-lg px-2 py-1 font-semibold {{ $perPage === $sizeOption ? 'bg-slate-200 text-slate-800' : 'text-slate-500 hover:bg-slate-100' }}"
                    @if($perPage === $sizeOption) aria-current="true" @endif
                >{{ $sizeOption }}</a>
            @endforeach
        </p>
    </div>

    @if($createdDateRange)
        <div class="px-5 pb-4 sm:px-6">
            <p class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                Report scope: {{ !empty($createdDateRange['label']) ? $createdDateRange['label'] : ($createdDateRange['from'].' to '.$createdDateRange['to']) }}
            </p>
        </div>
    @endif

    <div class="space-y-3 px-4 pb-4 lg:hidden">
        @forelse($tickets as $ticket)
            @php
                $createdLabel = $ticket->created_at->greaterThan(now()->subDay())
                    ? $ticket->created_at->diffForHumans()
                    : $ticket->created_at->format('M j, Y');
                $canManageTicket = auth()->user()?->can('manage', $ticket) ?? false;
                $requesterLabel = $ticket->requester_display_label;
                $creationSourceSummary = $ticket->creation_source_summary;
                $lastSeenTs = $ticketSeenTimestamps[(int) $ticket->id] ?? null;
                $isNew = $ticket->created_at->greaterThanOrEqualTo(now()->subDay())
                    && (!$lastSeenTs || $lastSeenTs < $ticket->created_at->timestamp);
                $completedAt = $ticket->closed_at ?? $ticket->resolved_at;
                $revertDeadline = $ticket->closed_at ? $ticket->closed_at->copy()->addDays($closedRevertWindowDays) : null;
                $canRevertTicket = $ticket->status === 'resolved'
                    || ($ticket->status === 'closed' && (! $revertDeadline || now()->lte($revertDeadline)));
            @endphp
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                @if($canDeleteTickets)
                    <div class="mb-3 flex items-center justify-end">
                        <label class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <input type="checkbox" class="js-ticket-checkbox ticket-checkbox ticket-checkbox--large" value="{{ $ticket->id }}">
                            <span>Select</span>
                        </label>
                    </div>
                @endif
                <a href="{{ route('admin.tickets.show', $ticket) }}" class="block">
                    <p class="wrap-anywhere text-sm font-semibold text-slate-900">({{ $ticket->ticket_number }}) {{ $ticket->subject }}</p>
                    <p class="mt-1 text-xs text-slate-600">{{ $ticket->category->name }} - Requester: {{ $requesterLabel }}</p>
                    <p class="mt-1 text-xs font-medium text-slate-500">{{ $creationSourceSummary }}</p>
                    <p class="mt-1 flex items-center gap-2 text-xs text-slate-500">
                        <span>Created {{ $createdLabel }}</span>
                        @if($isNew)
                            <span data-ticket-new-badge="1" class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-emerald-700">New</span>
                        @endif
                        @if($ticket->sla_badge_label)
                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold uppercase tracking-wide {{ $ticket->sla_badge_class }}">{{ $ticket->sla_badge_label }}</span>
                        @endif
                    </p>
                </a>

                <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600">
                    <div>
                        <span class="block text-xs uppercase tracking-wide text-slate-500">Assigned</span>
                        @if($canManageTicket && $ticket->assigned_user_ids !== [])
                            <button
                                type="button"
                                class="js-open-assign-modal assigned-tech-btn assigned-tech-btn--assigned"
                                data-ticket-id="{{ $ticket->id }}"
                                data-ticket-number="{{ $ticket->ticket_number }}"
                                data-assigned-to='@json($ticket->assigned_user_ids)'
                            >
                                {{ $ticket->assigned_users_label }}
                            </button>
                        @elseif($canManageTicket)
                            <button
                                type="button"
                                class="js-open-assign-modal assigned-tech-btn assigned-tech-btn--unassigned"
                                data-ticket-id="{{ $ticket->id }}"
                                data-ticket-number="{{ $ticket->ticket_number }}"
                                data-assigned-to="[]"
                            >
                                Assign
                            </button>
                        @else
                            <span>{{ $ticket->assigned_users_label }}</span>
                        @endif
                    </div>

                    @if($isHistoryTab)
                        <div>
                            <span class="block text-xs uppercase tracking-wide text-slate-500">Completed</span>
                            <span>{{ $completedAt ? $completedAt->format('M j, Y g:i A') : '-' }}</span>
                        </div>
                    @else
                        <div>
                            <span class="block text-xs uppercase tracking-wide text-slate-500">Activity</span>
                            <span>{{ $ticket->activity_label }}</span>
                        </div>
                    @endif
                </div>

                <div class="mt-3 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex min-w-16 items-center justify-center rounded-lg px-2.5 py-1 text-xs font-semibold {{ $ticket->priority_badge_class }}">
                            {{ $ticket->priority_label }}
                        </span>
                        <span class="inline-flex min-w-16 items-center justify-center rounded-lg px-2.5 py-1 text-xs font-semibold {{ $ticket->ticket_type_badge_class }}">
                            {{ $ticket->ticket_type_label }}
                        </span>
                        <span class="inline-flex min-w-16 items-center justify-center whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-semibold uppercase tracking-wide {{ $ticket->status_badge_class }}">
                            {{ $ticket->status_label }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-70"
                            data-ticket-copy-link
                            data-ticket-url="{{ route('admin.tickets.show', $ticket) }}"
                        >
                            Copy link
                        </button>
                        @if($canManageTicket && in_array($ticket->status, ['resolved', 'closed'], true))
                            @if($canRevertTicket)
                                <button
                                    type="button"
                                    class="js-open-revert-modal inline-flex items-center rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50"
                                    data-ticket-id="{{ $ticket->id }}"
                                    data-ticket-number="{{ $ticket->ticket_number }}"
                                >
                                    Revert
                                </button>
                            @else
                                <button
                                    type="button"
                                    class="inline-flex cursor-not-allowed items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-500"
                                    disabled
                                    title="Closed tickets cannot be reverted after {{ $closedRevertWindowDays }} days."
                                >
                                    Revert expired
                                </button>
                            @endif
                        @endif
                        @if($canManageTicket)
                            <button
                                type="button"
                                class="js-open-edit-modal inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                data-ticket-id="{{ $ticket->id }}"
                                data-ticket-number="{{ $ticket->ticket_number }}"
                                data-assigned-to='@json($ticket->assigned_user_ids)'
                                data-status="{{ $ticket->status }}"
                                data-priority="{{ $ticket->priority }}"
                                data-ticket-type="{{ $ticket->ticket_type }}"
                                data-can-revert="{{ $canRevertTicket ? '1' : '0' }}"
                            >
                                Edit
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-10 text-center">
                <p class="text-base font-semibold text-slate-700">No tickets found</p>
                <p class="mt-1 text-sm text-slate-500">Try adjusting your filters to broaden results.</p>
            </div>
        @endforelse
    </div>

    <div class="hidden max-h-[70vh] overflow-y-auto overflow-x-hidden lg:block">
        <table class="w-full table-fixed divide-y divide-slate-200 text-sm">
            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    @if($canDeleteTickets)
                        <th class="w-[4%] px-3 py-4 text-center">
                            <label class="inline-flex items-center justify-center">
                                <input id="select-all-tickets" type="checkbox" class="ticket-checkbox ticket-checkbox--large" aria-label="Select all visible tickets">
                            </label>
                        </th>
                    @endif
                    <th class="{{ $canDeleteTickets ? 'w-[27%]' : 'w-[31%]' }} px-4 py-4" aria-sort="{{ $sort === 'created' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><x-ui.sort-header key="created" :sort="$sort" :direction="$sortDirection">Details</x-ui.sort-header></th>
                    <th class="{{ $canDeleteTickets ? 'w-[13%]' : 'w-[14%]' }} px-3 py-4 text-center">Assigned Staff</th>
                    <th class="w-[12%] px-2 py-4 text-center" aria-sort="{{ $sort === 'severity' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><x-ui.sort-header key="severity" :sort="$sort" :direction="$sortDirection">Severity</x-ui.sort-header></th>
                    <th class="w-[8%] px-3 py-4 text-center">Type</th>
                    @if($isHistoryTab)
                        <th class="w-[12%] px-3 py-4 text-center">Completed At</th>
                    @else
                        <th class="w-[12%] px-3 py-4 text-center" aria-sort="{{ $sort === 'updated' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><x-ui.sort-header key="updated" :sort="$sort" :direction="$sortDirection">Activity Status</x-ui.sort-header></th>
                    @endif
                    <th class="w-[11%] px-3 py-4 text-center" aria-sort="{{ $sort === 'status' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><x-ui.sort-header key="status" :sort="$sort" :direction="$sortDirection">Status</x-ui.sort-header></th>
                    <th class="{{ $canDeleteTickets ? 'w-[13%]' : 'w-[12%]' }} px-3 py-4 text-center">Action</th>
                </tr>
            </thead>

            <tbody class="app-table-body divide-y divide-slate-200 bg-white">
                @forelse($tickets as $ticket)
                    @php
                        $createdLabel = $ticket->created_at->greaterThan(now()->subDay())
                            ? $ticket->created_at->diffForHumans()
                            : $ticket->created_at->format('M j, Y');
                        $canManageTicket = auth()->user()?->can('manage', $ticket) ?? false;
                        $requesterLabel = $ticket->requester_display_label;
                        $creationSourceSummary = $ticket->creation_source_summary;
                        $lastSeenTs = $ticketSeenTimestamps[(int) $ticket->id] ?? null;
                        $isNew = $ticket->created_at->greaterThanOrEqualTo(now()->subDay())
                            && (!$lastSeenTs || $lastSeenTs < $ticket->created_at->timestamp);
                        $completedAt = $ticket->closed_at ?? $ticket->resolved_at;
                        $revertDeadline = $ticket->closed_at ? $ticket->closed_at->copy()->addDays($closedRevertWindowDays) : null;
                        $canRevertTicket = $ticket->status === 'resolved'
                            || ($ticket->status === 'closed' && (! $revertDeadline || now()->lte($revertDeadline)));
                    @endphp

                    <tr class="admin-ticket-row transition hover:bg-slate-50">
                        @if($canDeleteTickets)
                            <td class="px-3 py-5 text-center align-middle">
                                <input type="checkbox" class="js-ticket-checkbox ticket-checkbox ticket-checkbox--large" value="{{ $ticket->id }}" aria-label="Select ticket {{ $ticket->ticket_number }}">
                            </td>
                        @endif
                        <td class="px-4 py-5 align-top">
                            <a href="{{ route('admin.tickets.show', $ticket) }}" class="block">
                                <p class="wrap-anywhere text-base font-semibold leading-snug text-slate-900">({{ $ticket->ticket_number }}) {{ $ticket->subject }}</p>
                                <p class="mt-0.5 break-words text-sm text-slate-600">{{ $ticket->category->name }} - Requester: {{ $requesterLabel }}</p>
                                <p class="mt-1 break-words text-xs font-medium text-slate-500">{{ $creationSourceSummary }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                                    <span>Created {{ $createdLabel }}</span>
                                    @if($isNew)
                                        <span data-ticket-new-badge="1" class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-emerald-700">New</span>
                                    @endif
                                    @if($ticket->sla_badge_label)
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold uppercase tracking-wide {{ $ticket->sla_badge_class }}">{{ $ticket->sla_badge_label }}</span>
                                    @endif
                                </p>
                            </a>
                        </td>

                        <td class="px-3 py-5 align-top text-center text-sm text-slate-700">
                            @if($canManageTicket && $ticket->assigned_user_ids !== [])
                                <button
                                    type="button"
                                    class="js-open-assign-modal assigned-tech-btn assigned-tech-btn--assigned max-w-full justify-center whitespace-normal break-words text-center leading-5"
                                    data-ticket-id="{{ $ticket->id }}"
                                    data-ticket-number="{{ $ticket->ticket_number }}"
                                    data-assigned-to='@json($ticket->assigned_user_ids)'
                                >
                                    {{ $ticket->assigned_users_label }}
                                </button>
                            @elseif($canManageTicket)
                                <button
                                    type="button"
                                    class="js-open-assign-modal assigned-tech-btn assigned-tech-btn--unassigned max-w-full justify-center whitespace-normal break-words text-center leading-5"
                                    data-ticket-id="{{ $ticket->id }}"
                                    data-ticket-number="{{ $ticket->ticket_number }}"
                                    data-assigned-to="[]"
                                >
                                    Assign
                                </button>
                            @else
                                <span>{{ $ticket->assigned_users_label }}</span>
                            @endif
                        </td>

                        <td class="px-2 py-5 text-center align-top">
                            <span class="inline-flex max-w-full whitespace-nowrap items-center justify-center rounded-lg px-2 py-1 text-xs font-semibold leading-5 {{ $ticket->priority_badge_class }}">
                                {{ $ticket->priority_label }}
                            </span>
                        </td>

                        <td class="px-3 py-5 text-center align-top">
                            <span class="inline-flex max-w-full whitespace-nowrap items-center justify-center rounded-lg px-2.5 py-1 text-xs font-semibold leading-5 {{ $ticket->ticket_type_badge_class }}">
                                {{ $ticket->ticket_type_label }}
                            </span>
                        </td>

                        @if($isHistoryTab)
                            <td class="px-3 py-5 text-center align-top text-sm leading-5 text-slate-700">
                                {{ $completedAt ? $completedAt->format('M j, Y \a\t g:i A') : '-' }}
                            </td>
                        @else
                            <td class="px-3 py-5 text-center align-top">
                                <span class="inline-flex max-w-full flex-wrap items-center justify-center gap-2 text-sm text-slate-600">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $ticket->activity_dot_class }}"></span>
                                    {{ $ticket->activity_label }}
                                </span>
                            </td>
                        @endif

                        <td class="px-3 py-5 text-center align-top">
                            <span class="inline-flex max-w-full whitespace-nowrap items-center justify-center rounded-lg px-2.5 py-1 text-xs font-semibold uppercase tracking-wide leading-5 {{ $ticket->status_badge_class }}">
                                {{ $ticket->status_label }}
                            </span>
                        </td>

                        <td class="px-3 py-5 text-center align-top">
                            <div class="flex flex-col items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex max-w-full items-center justify-center rounded-lg border border-slate-300 px-2.5 py-1.5 text-center text-xs font-semibold leading-5 text-slate-700 transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-70"
                                    data-ticket-copy-link
                                    data-ticket-url="{{ route('admin.tickets.show', $ticket) }}"
                                >
                                    Copy link
                                </button>
                                @if($canManageTicket && in_array($ticket->status, ['resolved', 'closed'], true))
                                    @if($canRevertTicket)
                                        <button
                                            type="button"
                                            class="js-open-revert-modal inline-flex max-w-full items-center justify-center rounded-lg border border-emerald-300 px-2.5 py-1.5 text-center text-xs font-semibold leading-5 text-emerald-700 transition hover:bg-emerald-50"
                                            data-ticket-id="{{ $ticket->id }}"
                                            data-ticket-number="{{ $ticket->ticket_number }}"
                                        >
                                            Revert
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            class="inline-flex max-w-full cursor-not-allowed items-center justify-center rounded-lg border border-slate-300 px-2.5 py-1.5 text-center text-xs font-semibold leading-5 text-slate-500"
                                            disabled
                                            title="Closed tickets cannot be reverted after {{ $closedRevertWindowDays }} days."
                                        >
                                            Revert expired
                                        </button>
                                    @endif
                                @endif
                                @if($canManageTicket)
                                    <button
                                        type="button"
                                        class="js-open-edit-modal inline-flex max-w-full items-center justify-center rounded-lg border border-slate-300 px-2.5 py-1.5 text-center text-xs font-semibold leading-5 text-slate-700 transition hover:bg-slate-50"
                                        data-ticket-id="{{ $ticket->id }}"
                                        data-ticket-number="{{ $ticket->ticket_number }}"
                                        data-assigned-to='@json($ticket->assigned_user_ids)'
                                        data-status="{{ $ticket->status }}"
                                        data-priority="{{ $ticket->priority }}"
                                        data-ticket-type="{{ $ticket->ticket_type }}"
                                        data-can-revert="{{ $canRevertTicket ? '1' : '0' }}"
                                    >
                                        Edit
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canDeleteTickets ? '8' : '7' }}" class="px-6 py-14 text-center">
                            <p class="text-base font-semibold text-slate-700">No tickets found</p>
                            <p class="mt-1 text-sm text-slate-500">Try adjusting your filters to broaden results.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tickets->count() > 0)
        <div data-admin-ticket-pagination class="border-t border-slate-200 px-6 py-4">
            {{ $tickets->appends($paginationQuery)->links() }}
        </div>
    @endif
</div>
