<div class="panel mb-6 overflow-hidden">
    <div class="flex flex-col gap-5 border-b border-slate-200 bg-slate-50 px-5 py-5 sm:px-6 2xl:flex-row 2xl:items-end 2xl:justify-between">
        <div>
            <h1 class="font-display text-3xl font-semibold text-slate-900">Statistics & Reports</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-600" data-reports-scope-note>
                Reports cover client tickets. Internal staff-to-staff tickets are left out, so totals here can be lower than on the dashboard, which counts every ticket.
            </p>
        </div>

        {{-- Below the widest screens the buttons sit under the title and wrap as a row, not as cramped columns beside it. --}}
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end [&_a]:whitespace-nowrap">
            <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-end gap-2" data-submit-feedback data-reports-filter-form>
                <input type="hidden" name="daily_month" value="{{ $dailyMonthKey }}">
                <input type="hidden" name="daily_date" value="{{ $dailySelectedDateValue }}">
                <div>
                    <label for="month" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Reporting Period</label>
                    <select id="month" name="month" data-auto-submit-change class="form-input min-w-[190px] py-2 text-sm">
                        @foreach($monthOptions as $option)
                            <option value="{{ $option['key'] }}" {{ $selectedMonthKey === $option['key'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <noscript>
                    <button type="submit" class="btn-secondary py-2">Apply</button>
                </noscript>
            </form>

            @unless($selectedMonthIsAllTime ?? false)
                <a href="{{ route('admin.reports.monthly.pdf', ['month' => $selectedMonthKey]) }}" class="btn-primary py-2">
                    Download Monthly PDF
                </a>
                <a href="{{ route('admin.reports.monthly.csv', ['month' => $selectedMonthKey]) }}" class="btn-secondary py-2">
                    Export CSV
                </a>
            @endunless
            <a href="{{ route('admin.reports.custom.index') }}" class="btn-secondary py-2">
                Custom Report
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 px-5 py-5 md:grid-cols-2 xl:grid-cols-3 sm:px-6">
        <x-ui.stat-card label="Total Tickets in Scope" :value="$periodOverview['total_tickets']">
            {{ $periodOverview['label'] }}
        </x-ui.stat-card>
        <x-ui.stat-card label="Major Issues / Incidents" :value="$majorIssueSummary['major_count'] ?? 0" value-class="text-red-600">
            Severity 1 tickets created this period
        </x-ui.stat-card>
        <x-ui.stat-card label="Resolution Rate" :value="number_format((float) ($selectedMonthRow['resolution_rate'] ?? 0), 1) . '%'" value-class="text-sky-600">
            Tickets created in the selected scope that are now resolved/closed. Cancelled tickets are not counted.
        </x-ui.stat-card>
    </div>

    @include('admin.reports.partials.sla-overview')
</div>
