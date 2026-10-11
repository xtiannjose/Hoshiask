@extends('layouts.app')

@section('title', 'Custom Report - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-semibold text-slate-900">Custom Report</h1>
            <p class="mt-1 text-sm text-slate-500">Build a report from any combination of filters and download it.</p>
            <p class="mt-1 max-w-2xl text-sm text-slate-600" data-reports-scope-note>Like the Reports page, this covers client tickets. Internal staff-to-staff tickets are left out.</p>
        </div>
        <a href="{{ route('admin.reports.index') }}" class="btn-secondary">Back to Reports</a>
    </div>

    <form method="GET" action="{{ route('admin.reports.custom.index') }}" class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-4">
            <div>
                <label for="created_from" class="form-label">From</label>
                <input type="date" id="created_from" name="created_from" class="form-input" value="{{ request('created_from', $dateRange['from']) }}">
            </div>
            <div>
                <label for="created_to" class="form-label">To</label>
                <input type="date" id="created_to" name="created_to" class="form-input" value="{{ request('created_to', $dateRange['to']) }}">
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ \App\Enums\TicketStatus::tryFrom($status)?->label() ?? ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="priority" class="form-label">Severity</label>
                <select id="priority" name="priority" class="form-input">
                    <option value="all" {{ request('priority', 'all') === 'all' ? 'selected' : '' }}>All Severities</option>
                    @foreach($priorities as $priority)
                        <option value="{{ $priority }}" {{ request('priority') === $priority ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $priority)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="category" class="form-label">Category</label>
                <select id="category" name="category" class="form-input">
                    <option value="all" {{ request('category', 'all') === 'all' ? 'selected' : '' }}>All categories</option>
                    <x-ui.category-options :categories="$categories" :selected="request('category')" />
                </select>
            </div>
            <div>
                <label for="department" class="form-label">Field Office</label>
                <select id="department" name="department" class="form-input">
                    <option value="all" {{ request('department', 'all') === 'all' ? 'selected' : '' }}>All offices</option>
                    @foreach($departments as $department)
                        <option value="{{ $department }}" {{ request('department') === $department ? 'selected' : '' }}>{{ $department }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="assigned_to" class="form-label">Assignee</label>
                <select id="assigned_to" name="assigned_to" class="form-input">
                    <option value="all" {{ request('assigned_to', 'all') === 'all' ? 'selected' : '' }}>All assignees</option>
                    <option value="0" {{ (string) request('assigned_to') === '0' ? 'selected' : '' }}>Unassigned</option>
                    <x-ui.assignee-options :assignees="$assignees" :selected="request('assigned_to')" />
                </select>
            </div>
            <div>
                <label for="layout" class="form-label">Layout</label>
                <select id="layout" name="layout" class="form-input">
                    <option value="summary" {{ $layout === 'summary' ? 'selected' : '' }}>Summary counts</option>
                    <option value="detailed" {{ $layout === 'detailed' ? 'selected' : '' }}>Detailed list</option>
                </select>
            </div>
            <div>
                <label for="group_by" class="form-label">Group By (summary only)</label>
                <select id="group_by" name="group_by" class="form-input">
                    <option value="status" {{ $groupBy === 'status' ? 'selected' : '' }}>Status</option>
                    <option value="priority" {{ $groupBy === 'priority' ? 'selected' : '' }}>Severity</option>
                    <option value="category" {{ $groupBy === 'category' ? 'selected' : '' }}>Category</option>
                    <option value="root_cause" {{ $groupBy === 'root_cause' ? 'selected' : '' }}>Root Cause</option>
                    <option value="assignee" {{ $groupBy === 'assignee' ? 'selected' : '' }}>Assignee</option>
                    <option value="department" {{ $groupBy === 'department' ? 'selected' : '' }}>Field Office</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary">Generate Report</button>
            <a href="{{ route('admin.reports.custom.csv', request()->query()) }}" class="btn-secondary">Export CSV</a>
            <a href="{{ route('admin.reports.custom.pdf', request()->query()) }}" class="btn-secondary">Export PDF</a>
        </div>
    </form>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        @foreach($dateRange['notices'] ?? [] as $dateNotice)
            <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status" data-report-date-notice>
                {{ $dateNotice }}
            </div>
        @endforeach
        <p class="mb-4 text-sm text-slate-500">Period: {{ $dateRange['from'] }} to {{ $dateRange['to'] }}</p>

        @if($layout === 'summary')
            <table class="min-w-full divide-y divide-slate-200">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">{{ ucfirst(str_replace('_', ' ', $groupBy)) }}</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Count</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Share</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($summaryRows as $row)
                        <tr>
                            <td class="px-3 py-2 text-sm text-slate-700">{{ $row['label'] }}</td>
                            <td class="px-3 py-2 text-sm font-semibold text-slate-900">{{ $row['count'] }}</td>
                            <td class="px-3 py-2 text-sm text-slate-500">{{ $row['share'] }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-6 text-center text-sm text-slate-500">No tickets match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <p class="mt-3 text-sm text-slate-500">Total: {{ $totalCount }} ticket(s)</p>
        @else
            <div class="relative overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Ticket #</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Subject</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Severity</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Office</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Assignee</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Created</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Root Cause</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($detailedRows as $ticket)
                            <tr>
                                <td class="px-3 py-2 text-sm text-slate-700">
                                    <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-hoshishi-primary hover:underline">{{ $ticket->ticket_number }}</a>
                                </td>
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $ticket->subject }}</td>
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $ticket->status_label }}</td>
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $ticket->priority_label }}</td>
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $ticket->department }}</td>
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $ticket->assignedUser?->publicDisplayName() ?? 'Unassigned' }}</td>
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $ticket->created_at->format('M j, Y') }}</td>
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $ticket->root_cause_category_label ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">No tickets match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $detailedRows->links() }}</div>
            <p class="mt-3 text-sm text-slate-500">Total: {{ $detailedRows->total() }} ticket(s)</p>
            @if($detailedRows->total() > \App\Services\Admin\Reports\CustomReportService::PDF_MAX_ROWS)
                <p class="mt-1 text-sm text-slate-500" data-report-pdf-limit>The PDF lists only the newest {{ \App\Services\Admin\Reports\CustomReportService::PDF_MAX_ROWS }} tickets. Use Export CSV for the complete list.</p>
            @endif
        @endif
    </div>
</div>
@endsection
