<div class="panel order-3 p-5 sm:p-6">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-xl font-semibold text-slate-900">Total Tickets Breakdown</h2>
        </div>
        <button type="button" class="btn-secondary py-2 text-xs js-open-volume-chart" data-chart-title="Total Tickets Breakdown (Detailed View)" data-chart-source="total-tickets-breakdown-chart">
            Enlarge
        </button>
    </div>
    <div id="total-tickets-breakdown-chart" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="mb-2 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Tickets</p>
            <span class="text-xs text-slate-500">{{ $mixScopeLabel }} &middot; {{ $pieTotalCreated }} total</span>
        </div>
        <div class="grid gap-4 md:grid-cols-[auto_1fr] md:items-center">
            <div class="flex justify-center">
                <svg viewBox="0 0 180 180" class="js-total-pie-chart h-44 w-44" role="img" aria-label="Total tickets breakdown">
                    <circle cx="90" cy="90" r="{{ $pieRadius }}" fill="none" class="pie-track" stroke-width="20"></circle>
                    @if($ticketPie['total'] > 0)
                        @foreach($ticketPie['segments'] as $segment)
                            <circle
                                cx="90"
                                cy="90"
                                r="{{ $pieRadius }}"
                                fill="none"
                                stroke="{{ $segment['color'] }}"
                                stroke-width="20"
                                stroke-linecap="butt"
                                stroke-dasharray="{{ $segment['length'] }} {{ $pieCircumference }}"
                                stroke-dashoffset="{{ $segment['offset'] }}"
                                transform="rotate(-90 90 90)"
                            ></circle>
                        @endforeach
                    @endif
                    <text x="90" y="84" text-anchor="middle" class="pie-center-label" font-size="11" font-weight="600">Total</text>
                    <text x="90" y="104" text-anchor="middle" class="pie-center-value" font-size="24" font-weight="700">{{ $pieTotalCreated }}</text>
                </svg>
            </div>
            <div class="space-y-2">
                @foreach($ticketPie['slices'] as $slice)
                    @php($sliceCount = (int) ($slice['display_count'] ?? $slice['count'] ?? 0))
                    <a href="{{ $slice['link'] }}" class="pie-legend-row group flex items-center justify-between rounded-lg bg-slate-100 px-3 py-2 text-sm transition hover:bg-slate-200">
                        <div class="flex items-center gap-2">
                            <svg class="h-2.5 w-2.5" viewBox="0 0 10 10" aria-hidden="true">
                                <circle cx="5" cy="5" r="5" fill="{{ $slice['color'] }}"></circle>
                            </svg>
                            <span class="pie-legend-text text-slate-600">{{ $slice['label'] }}</span>
                        </div>
                        <div class="text-right">
                            <span class="pie-legend-value font-semibold text-slate-900">{{ $sliceCount }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="panel order-4 p-5 sm:p-6">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-xl font-semibold text-slate-900">Category Mix</h2>
        </div>
        <button type="button" class="btn-secondary py-2 text-xs js-open-volume-chart" data-chart-title="Category Mix (Detailed View)" data-chart-source="category-mix-chart">
            Enlarge
        </button>
    </div>
    <div id="category-mix-chart" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div class="mb-2 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">By Category</p>
            <span class="text-xs text-slate-500">{{ $mixScopeLabel }} &middot; {{ $categoryPie['total'] }} total</span>
        </div>
        <div class="grid gap-3 sm:grid-cols-[auto_1fr] sm:items-center">
            <div class="flex justify-center">
                <svg viewBox="0 0 180 180" class="js-total-pie-chart h-40 w-40" role="img" aria-label="Tickets by category">
                    <circle cx="90" cy="90" r="{{ $pieRadius }}" fill="none" class="pie-track" stroke-width="20"></circle>
                    @if($categoryPie['total'] > 0)
                        @foreach($categoryPie['segments'] as $segment)
                            <circle
                                cx="90"
                                cy="90"
                                r="{{ $pieRadius }}"
                                fill="none"
                                stroke="{{ $segment['color'] }}"
                                stroke-width="20"
                                stroke-linecap="butt"
                                stroke-dasharray="{{ $segment['length'] }} {{ $pieCircumference }}"
                                stroke-dashoffset="{{ $segment['offset'] }}"
                                transform="rotate(-90 90 90)"
                            ></circle>
                        @endforeach
                    @endif
                    <text x="90" y="84" text-anchor="middle" class="pie-center-label" font-size="11" font-weight="600">Category</text>
                    <text x="90" y="104" text-anchor="middle" class="pie-center-value" font-size="20" font-weight="700">{{ $categoryPie['total'] }}</text>
                </svg>
            </div>
            <div class="space-y-1.5">
                @foreach($categoryPie['slices'] as $slice)
                    <a href="{{ $slice['link'] }}" class="pie-legend-row group flex items-center justify-between rounded-lg bg-slate-100 px-3 py-2 text-sm transition">
                        <div class="flex items-center gap-2">
                            <svg class="h-2.5 w-2.5" viewBox="0 0 10 10" aria-hidden="true">
                                <circle cx="5" cy="5" r="5" fill="{{ $slice['color'] }}"></circle>
                            </svg>
                            <span class="pie-legend-text text-slate-600">{{ $slice['label'] }}</span>
                        </div>
                        <div class="text-right">
                            <span class="pie-legend-value font-semibold text-slate-900">{{ $slice['count'] }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="panel order-5 p-5 sm:p-6">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-xl font-semibold text-slate-900">Severity Mix</h2>
        </div>
        <button type="button" class="btn-secondary py-2 text-xs js-open-volume-chart" data-chart-title="Severity Mix (Detailed View)" data-chart-source="priority-mix-chart">
            Enlarge
        </button>
    </div>
    <div id="priority-mix-chart" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div class="mb-2 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">By Severity</p>
            <span class="text-xs text-slate-500">{{ $mixScopeLabel }} &middot; {{ $priorityPie['total'] }} total</span>
        </div>
        <div class="grid gap-3 sm:grid-cols-[auto_1fr] sm:items-center">
            <div class="flex justify-center">
                <svg viewBox="0 0 180 180" class="js-total-pie-chart h-40 w-40" role="img" aria-label="Tickets by severity">
                    <circle cx="90" cy="90" r="{{ $pieRadius }}" fill="none" class="pie-track" stroke-width="20"></circle>
                    @if($priorityPie['total'] > 0)
                        @foreach($priorityPie['segments'] as $segment)
                            <circle
                                cx="90"
                                cy="90"
                                r="{{ $pieRadius }}"
                                fill="none"
                                stroke="{{ $segment['color'] }}"
                                stroke-width="20"
                                stroke-linecap="butt"
                                stroke-dasharray="{{ $segment['length'] }} {{ $pieCircumference }}"
                                stroke-dashoffset="{{ $segment['offset'] }}"
                                transform="rotate(-90 90 90)"
                            ></circle>
                        @endforeach
                    @endif
                    <text x="90" y="84" text-anchor="middle" class="pie-center-label" font-size="11" font-weight="600">Severity</text>
                    <text x="90" y="104" text-anchor="middle" class="pie-center-value" font-size="20" font-weight="700">{{ $priorityPie['total'] }}</text>
                </svg>
            </div>
            <div class="space-y-1.5">
                @foreach($priorityPie['slices'] as $slice)
                    <a href="{{ $slice['link'] }}" class="pie-legend-row group flex items-center justify-between rounded-lg bg-slate-100 px-3 py-2 text-sm transition">
                        <div class="flex items-center gap-2">
                            <svg class="h-2.5 w-2.5" viewBox="0 0 10 10" aria-hidden="true">
                                <circle cx="5" cy="5" r="5" fill="{{ $slice['color'] }}"></circle>
                            </svg>
                            <span class="pie-legend-text text-slate-600">{{ $slice['label'] }}</span>
                        </div>
                        <div class="text-right">
                            <span class="pie-legend-value font-semibold text-slate-900">{{ $slice['count'] }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Operational KPIs</h3>
            </div>
            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                Live system view
            </span>
        </div>
        <div class="grid gap-3 md:grid-cols-3">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Open Tickets</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ $stats['open_tickets'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-emerald-700">Current active queue</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Unassigned Open</p>
                <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $stats['unassigned_open_tickets'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-amber-700">Needs routing attention</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Severity 1 Open</p>
                <p class="mt-1 text-2xl font-semibold text-red-900">{{ $stats['severity_one_open_tickets'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-red-700">Priority incidents in progress</p>
            </div>
        </div>
    </div>
</div>
