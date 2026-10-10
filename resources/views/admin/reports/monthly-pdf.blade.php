<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Operations Report {{ $selectedMonthKey }}</title>
    <style>
        @page {
            margin: 24px 28px;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.45;
        }

        .header-wrap {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            margin-bottom: 14px;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            padding: 10px 12px;
            vertical-align: top;
        }

        .header-brand {
            width: 62%;
            border-right: 1px solid #cbd5e1;
        }

        .brand-title {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .brand-subtitle {
            margin: 2px 0 0;
            color: #475569;
            font-size: 11px;
        }

        .meta-label {
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 2px;
        }

        .meta-value {
            margin: 0 0 8px;
            color: #0f172a;
            font-weight: 600;
            font-size: 11px;
        }

        .section {
            margin-top: 14px;
        }

        .section-title {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }

        .kpi-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 6px;
            margin: 0 -6px;
        }

        .kpi-grid td {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 8px 9px;
            width: 33.33%;
            vertical-align: top;
        }

        .kpi-label {
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .kpi-value {
            margin-top: 4px;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .kpi-note {
            margin-top: 4px;
            color: #64748b;
            font-size: 9px;
        }

        .delta-up {
            color: #047857;
        }

        .delta-down {
            color: #b91c1c;
        }

        .delta-flat {
            color: #475569;
        }

        .two-col {
            width: 100%;
            border-collapse: collapse;
        }

        .two-col td {
            width: 50%;
            vertical-align: top;
        }

        .two-col td:first-child {
            padding-right: 6px;
        }

        .two-col td:last-child {
            padding-left: 6px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #d1d9e6;
            padding: 6px 8px;
            text-align: left;
        }

        .report-table th {
            background: #eef3f8;
            color: #1e293b;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .report-table td.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .report-table tbody tr:nth-child(even) td {
            background: #fafcff;
        }

        .selected-row td {
            background: #e5f3ff !important;
            font-weight: 700;
        }

        .muted {
            color: #64748b;
        }

        .small {
            font-size: 9px;
        }

        .footnote {
            margin-top: 12px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            color: #64748b;
            font-size: 9px;
        }
    </style>
</head>
    <body>
    @php
        $received = (int) ($selectedMonthRow['received'] ?? 0);
        $completed = (int) ($selectedMonthRow['resolved'] ?? 0);
        $completionRate = (float) ($selectedMonthRow['resolution_rate'] ?? 0);
        $resolvedStatusCount = (int) ($statusBreakdown['resolved'] ?? 0);
        $closedStatusCount = (int) ($statusBreakdown['closed'] ?? 0);
    @endphp

    <div class="header-wrap">
        <table class="header">
            <tr>
                <td class="header-brand">
                    <h1 class="brand-title">{{ config('app.name') }}</h1>
                    <p class="brand-subtitle">Monthly Operations Report</p>
                    <p class="brand-subtitle">Managed by {{ \App\Models\User::supportOrganizationName() }}</p>
                    <p class="brand-subtitle">Period: {{ $selectedMonthRow['month_label'] ?? $selectedMonthRange['label'] }}</p>
                </td>
                <td>
                    <div class="meta-label">Coverage</div>
                    <p class="meta-value">{{ $selectedMonthRange['start']->format('M d, Y') }} - {{ $selectedMonthRange['end']->format('M d, Y') }}</p>
                    <div class="meta-label">Generated</div>
                    <p class="meta-value">{{ $generatedAt->format('M d, Y h:i A') }}</p>
                    <div class="meta-label">Report Key</div>
                    <p class="meta-value">{{ $selectedMonthKey }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Executive Summary</h2>
        <table class="kpi-grid">
            <tr>
                <td>
                    <div class="kpi-label">Tickets Received</div>
                    <div class="kpi-value">{{ $received }}</div>
                    <div class="kpi-note">Created during this reporting month</div>
                </td>
                <td>
                    <div class="kpi-label">Tickets Completed</div>
                    <div class="kpi-value">{{ $completed }}</div>
                    <div class="kpi-note">Created this month that are now resolved/closed</div>
                </td>
                <td>
                    <div class="kpi-label">Completion Rate</div>
                    <div class="kpi-value">{{ number_format($completionRate, 1) }}%</div>
                    <div class="kpi-note">Created this month that are now resolved/closed</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Workload Mix (Created In Month)</h2>
        <table class="two-col">
            <tr>
                <td>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th style="width: 80px;">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(\App\Enums\TicketStatus::cases() as $status)
                                @php
                                    $statusCount = match ($status) {
                                        \App\Enums\TicketStatus::Resolved => $resolvedStatusCount,
                                        \App\Enums\TicketStatus::Closed => $closedStatusCount,
                                        default => (int) ($statusBreakdown[$status->value] ?? 0),
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $status->label() }}</td>
                                    <td class="num">{{ $statusCount }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
                <td>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Severity</th>
                                <th style="width: 80px;">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach([null, ...\App\Enums\TicketPriority::cases()] as $priority)
                                @php
                                    $priorityCount = (int) ($priorityBreakdown[$priority?->value ?? 'pending_review'] ?? 0);
                                @endphp
                                <tr>
                                    <td>{{ $priority?->label() ?? \App\Enums\TicketPriority::SEVERITY_NOT_SET_LABEL }}</td>
                                    <td class="num">{{ $priorityCount }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Category Breakdown (Created In Month)</h2>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th style="width: 90px;">Count</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categoryBreakdown as $category)
                    <tr>
                        <td>{{ $category['name'] }}</td>
                        <td class="num">{{ $category['count'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="muted">No tickets were created in this reporting month.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footnote">
        <div><strong>Notes:</strong> "Completed" and "Resolved" include tickets created in the reporting month that are now resolved or closed. "Closed" remains listed separately as a subset of those resolved tickets.</div>
        <div class="small">Generated by {{ config('app.name') }} Reporting Module.</div>
    </div>
</body>
</html>
