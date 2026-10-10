<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RecordsExports;
use App\Http\Controllers\Concerns\RendersPdfDownloads;
use App\Http\Controllers\Concerns\SanitizesCsvCells;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\Admin\Reports\CustomReportService;
use App\Services\Admin\TicketIndexOptionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomReportController extends Controller
{
    use RecordsExports;
    use RendersPdfDownloads;
    use SanitizesCsvCells;

    public function __construct(
        private CustomReportService $customReports,
        private TicketIndexOptionService $ticketOptions,
    ) {}

    public function index(Request $request)
    {
        $layout = $request->string('layout')->toString() === 'detailed' ? 'detailed' : 'summary';
        $groupBy = $request->string('group_by')->toString() ?: 'status';
        $dateRange = $this->customReports->resolveDateRange($request);
        $query = $this->customReports->buildFilteredQuery($request, $dateRange);

        $summaryRows = $layout === 'summary' ? $this->customReports->buildSummaryRows($query, $groupBy) : null;
        $detailedRows = $layout === 'detailed' ? $this->customReports->buildDetailedRows($query) : null;
        $totalCount = $summaryRows?->sum('count');

        return view('admin.reports.custom.index', [
            'layout' => $layout,
            'groupBy' => $groupBy,
            'dateRange' => $dateRange,
            'summaryRows' => $summaryRows,
            'detailedRows' => $detailedRows,
            'totalCount' => $totalCount,
            'statuses' => Ticket::STATUSES,
            'priorities' => Ticket::PRIORITIES,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'departments' => $this->customReports->departmentOptions(),
            'assignees' => $this->ticketOptions->activeAssignableAgents(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $layout = $request->string('layout')->toString() === 'detailed' ? 'detailed' : 'summary';
        $groupBy = $request->string('group_by')->toString() ?: 'status';
        $dateRange = $this->customReports->resolveDateRange($request);
        $query = $this->customReports->buildFilteredQuery($request, $dateRange);

        $this->recordExport($request, 'export.report_custom_csv', 'Exported a custom report to CSV.', (clone $query)->count(), [
            'layout' => $layout,
            'group_by' => $groupBy,
        ]);

        $filename = 'custom-ticket-report-'.now()->format('Y-m-d-His').'.csv';

        return $this->streamCsvDownload($filename, function ($handle) use ($query, $layout, $groupBy, $dateRange): void {
            fputcsv($handle, ['Custom Ticket Report']);
            fputcsv($handle, ['Period', $dateRange['from'].' to '.$dateRange['to']]);
            foreach ($dateRange['notices'] ?? [] as $dateNotice) {
                fputcsv($handle, ['Note', $dateNotice]);
            }
            fputcsv($handle, []);

            if ($layout === 'summary') {
                fputcsv($handle, [ucfirst(str_replace('_', ' ', $groupBy)), 'Count', 'Share (%)']);
                foreach ($this->customReports->buildSummaryRows($query, $groupBy) as $row) {
                    fputcsv($handle, [$this->csvCell($row['label']), $row['count'], $row['share']]);
                }
            } else {
                fputcsv($handle, ['Ticket #', 'Subject', 'Status', 'Severity', 'Category', 'Office', 'Assignee', 'Created At', 'Resolved At', 'Root Cause', 'Cancellation Reason']);
                (clone $query)->chunk(200, function ($tickets) use ($handle): void {
                    foreach ($tickets as $ticket) {
                        /** @var Ticket $ticket */
                        fputcsv($handle, [
                            $this->csvCell($ticket->ticket_number),
                            $this->csvCell($ticket->subject),
                            $this->csvCell($ticket->status_label),
                            $this->csvCell($ticket->priority_label),
                            $this->csvCell($ticket->category?->name),
                            $this->csvCell($ticket->department),
                            $this->csvCell($ticket->assignedUser?->publicDisplayName()),
                            optional($ticket->created_at)->format('Y-m-d H:i:s'),
                            optional($ticket->resolved_at)->format('Y-m-d H:i:s'),
                            $this->csvCell($ticket->root_cause_category_label),
                            $this->csvCell($ticket->cancellation_reason),
                        ]);
                    }
                });
            }
        });
    }

    public function pdf(Request $request)
    {
        $layout = $request->string('layout')->toString() === 'detailed' ? 'detailed' : 'summary';
        $groupBy = $request->string('group_by')->toString() ?: 'status';
        $dateRange = $this->customReports->resolveDateRange($request);
        $query = $this->customReports->buildFilteredQuery($request, $dateRange);

        $summaryRows = $layout === 'summary' ? $this->customReports->buildSummaryRows($query, $groupBy) : null;
        $detailedRows = $layout === 'detailed'
            ? (clone $query)->latest('created_at')->limit(CustomReportService::PDF_MAX_ROWS)->get()
            : null;
        $detailedTotal = $layout === 'detailed' ? (clone $query)->count() : null;

        $this->recordExport($request, 'export.report_custom_pdf', 'Exported a custom report to PDF.', $detailedTotal ?? (clone $query)->count(), [
            'layout' => $layout,
            'group_by' => $groupBy,
            'rows_shown' => $detailedRows?->count(),
        ]);

        return $this->downloadA4Pdf('admin.reports.custom.pdf', [
            'layout' => $layout,
            'groupBy' => $groupBy,
            'dateRange' => $dateRange,
            'summaryRows' => $summaryRows,
            'detailedRows' => $detailedRows,
            'detailedTotal' => $detailedTotal,
        ], 'custom-ticket-report-'.now()->format('Y-m-d-His').'.pdf');
    }
}
