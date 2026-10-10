<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Custom Ticket Report</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; font-size: 11px; line-height: 1.45; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p.meta { color: #475569; margin: 0 0 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: left; }
        th { background: #f1f5f9; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; }
    </style>
</head>
<body>
    <h1>Custom Ticket Report</h1>
    <p class="meta">Period: {{ $dateRange['from'] }} to {{ $dateRange['to'] }}</p>
    @foreach($dateRange['notices'] ?? [] as $dateNotice)
        <p class="meta">Note: {{ $dateNotice }}</p>
    @endforeach

    @if($layout === 'summary')
        <table>
            <thead>
                <tr>
                    <th>{{ ucfirst(str_replace('_', ' ', $groupBy)) }}</th>
                    <th>Count</th>
                    <th>Share</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summaryRows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>{{ $row['count'] }}</td>
                        <td>{{ $row['share'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="3">No tickets match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    @else
        @if(($detailedTotal ?? 0) > $detailedRows->count())
            <p class="meta">Showing the {{ $detailedRows->count() }} newest of {{ $detailedTotal }} matching tickets. Download the CSV for the complete list.</p>
        @endif
        <table>
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Severity</th>
                    <th>Office</th>
                    <th>Assignee</th>
                    <th>Created</th>
                    <th>Root Cause</th>
                </tr>
            </thead>
            <tbody>
                @forelse($detailedRows as $ticket)
                    <tr>
                        <td>{{ $ticket->ticket_number }}</td>
                        <td>{{ $ticket->subject }}</td>
                        <td>{{ $ticket->status_label }}</td>
                        <td>{{ $ticket->priority_label }}</td>
                        <td>{{ $ticket->department }}</td>
                        <td>{{ $ticket->assignedUser?->publicDisplayName() ?? 'Unassigned' }}</td>
                        <td>{{ $ticket->created_at->format('M j, Y') }}</td>
                        <td>{{ $ticket->root_cause_category_label ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8">No tickets match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>
