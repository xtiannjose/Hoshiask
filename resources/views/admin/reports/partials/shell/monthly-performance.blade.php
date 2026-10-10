<div class="panel order-2 p-5 sm:p-6">
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <h2 class="font-display text-xl font-semibold text-slate-900">Monthly Performance (Last 12 Months)</h2>
        </div>
        <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-end gap-2" data-submit-feedback data-reports-filter-form>
            <input type="hidden" name="daily_month" value="{{ $dailyMonthKey }}">
            <input type="hidden" name="daily_date" value="{{ $dailySelectedDateValue }}">
            <div>
                <label for="monthly-focus-month" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Focus Month</label>
                <select id="monthly-focus-month" name="month" data-auto-submit-change class="form-input min-w-[190px] py-2 text-sm">
                    @foreach($monthOptions->where('key', '!=', 'all') as $option)
                        <option value="{{ $option['key'] }}" {{ $monthlyPerformanceFocusMonthKey === $option['key'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <noscript>
                <button type="submit" class="btn-secondary py-2">Apply</button>
            </noscript>
        </form>
    </div>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div class="mb-3 flex flex-wrap items-center gap-4 text-xs text-slate-600">
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span>Received</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Completed</span>
        </div>
        <div class="w-full overflow-hidden">
            <svg viewBox="0 0 {{ $monthlyPerformance['chart_width'] }} {{ $monthlyPerformance['chart_height'] }}" class="h-80 w-full">
                @foreach($monthlyPerformance['grid_lines'] as $line)
                    <line x1="{{ $monthlyPerformance['padding_left'] }}" y1="{{ $line['y'] }}" x2="{{ $monthlyPerformance['chart_width'] - $monthlyPerformance['padding_right'] }}" y2="{{ $line['y'] }}" stroke="currentColor" class="text-slate-600" stroke-width="1" stroke-dasharray="3 4"></line>
                    <text x="6" y="{{ $line['y'] + 4 }}" fill="currentColor" class="text-slate-500" font-size="10">{{ $line['count_label'] }}</text>
                @endforeach

                @foreach($monthlyPerformance['bars'] as $bar)
                    <rect x="{{ $bar['center_x'] - $bar['bar_width'] - 2 }}" y="{{ $bar['received_y'] }}" width="{{ $bar['bar_width'] }}" height="{{ $bar['received_height'] }}" rx="2" fill="#0ea5e9"></rect>
                    <rect x="{{ $bar['center_x'] + 2 }}" y="{{ $bar['resolved_y'] }}" width="{{ $bar['bar_width'] }}" height="{{ $bar['resolved_height'] }}" rx="2" fill="#10b981"></rect>
                    <text x="{{ $bar['center_x'] }}" y="{{ $bar['label_y'] }}" fill="currentColor" class="text-slate-500" font-size="10" text-anchor="middle">{{ $bar['label'] }}</text>
                @endforeach
            </svg>
        </div>
    </div>
    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Detailed Month</p>
            <p class="mt-1 text-base font-semibold text-slate-900">{{ $monthlyPerformance['selected_point']['month_label'] ?? ($selectedMonthRow['month_label'] ?? '') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Received</p>
            <p class="mt-1 text-2xl font-semibold text-sky-600">{{ $monthlyPerformance['selected_point']['received'] ?? ($selectedMonthRow['received'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ $monthlyPerformance['selected_point']['resolved'] ?? ($selectedMonthRow['resolved'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completion Rate</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format((float) ($monthlyPerformance['selected_point']['resolution_rate'] ?? ($selectedMonthRow['resolution_rate'] ?? 0)), 1) }}%</p>
        </div>
    </div>
</div>
