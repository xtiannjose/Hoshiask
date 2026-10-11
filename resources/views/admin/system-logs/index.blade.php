@extends('layouts.app')

@section('title', 'System Logs - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-semibold text-slate-900">System Logs</h1>
            <p class="mt-1 text-sm text-slate-500">Major platform activity and account-setting change history.</p>
        </div>
        <form method="POST" action="{{ route('admin.system-logs.lock') }}" data-submit-feedback>
            @csrf
            <button type="submit" class="btn-secondary" data-loading-text="Locking...">Lock Logs</button>
        </form>
    </div>

    <form method="GET" action="{{ route('admin.system-logs.index') }}" class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" data-submit-feedback>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div>
                <label for="search" class="form-label">Search</label>
                <input
                    id="search"
                    name="search"
                    type="text"
                    class="form-input"
                    value="{{ request('search') }}"
                    placeholder="Event, actor, details"
                >
            </div>
            <div>
                <label for="category" class="form-label">Category</label>
                <select id="category" name="category" class="form-input">
                    <option value="">All</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ ucfirst($category) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="event" class="form-label">Event</label>
                <select id="event" name="event" class="form-input">
                    <option value="">All</option>
                    @foreach($events as $event)
                        <option value="{{ $event }}" @selected(request('event') === $event)>{{ $event }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary w-full" data-loading-text="Applying...">Apply</button>
                <a href="{{ route('admin.system-logs.index') }}" class="btn-secondary whitespace-nowrap">Reset</a>
            </div>
        </div>
    </form>

    <div class="mb-5 flex justify-end">
        <a href="{{ route('admin.system-logs.export', request()->only(['search', 'category', 'event'])) }}" class="btn-secondary">
            Export Audit Trail (CSV)
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="relative overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Actor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Event</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Metadata</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">IP</th>
                    </tr>
                </thead>
                <tbody class="app-table-body divide-y divide-slate-100">
                    @forelse($logs as $log)
                        <tr>
                            <td class="px-4 py-3 align-top text-xs text-slate-600">
                                {{ optional($log->occurred_at)->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-4 py-3 align-top text-sm text-slate-700">
                                <div class="font-semibold text-slate-900">{{ optional($log->actor)->name ?? 'System' }}</div>
                                <div class="text-xs text-slate-500">{{ optional($log->actor)->email ?? 'N/A' }}</div>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $log->event_type }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ ucfirst($log->category) }}</div>
                            </td>
                            <td class="px-4 py-3 align-top text-sm text-slate-700">{{ $log->description }}</td>
                            <td class="px-4 py-3 align-top text-xs text-slate-600">
                                @if(!empty($log->metadata))
                                    <pre class="max-w-[28rem] overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-2 text-xs text-slate-700">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @else
                                    <span class="text-slate-500">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top text-xs text-slate-600">{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                No logs found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
