<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RecordsExports;
use App\Http\Controllers\Concerns\RendersPdfDownloads;
use App\Http\Controllers\Concerns\SanitizesCsvCells;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\Reports\ReportPageDataService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use RecordsExports;
    use RendersPdfDownloads;
    use SanitizesCsvCells;

    public function __construct(
        private ReportPageDataService $reportPageData,
    ) {}

    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $viewData = $this->reportPageData->buildIndexViewData($request, $user);

        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('admin.reports.partials.shell', $viewData)->render(),
            ]);
        }

        return view('admin.reports.index', $viewData);
    }

    public function monthlyPdf(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $viewData = $this->reportPageData->buildMonthlyPdfViewData($request, $user);

        $this->recordMonthlyExport($request, 'export.report_monthly_pdf', 'Exported the monthly report to PDF.', $viewData);

        return $this->downloadA4Pdf('admin.reports.monthly-pdf', $viewData, 'ticket-monthly-report-'.$viewData['selectedMonthKey'].'.pdf');
    }

    public function monthlyCsv(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $viewData = $this->reportPageData->buildMonthlyPdfViewData($request, $user);

        $this->recordMonthlyExport($request, 'export.report_monthly_csv', 'Exported the monthly report to CSV.', $viewData);

        $filename = 'ticket-monthly-report-'.$viewData['selectedMonthKey'].'.csv';

        return $this->streamCsvDownload($filename, function ($handle) use ($viewData): void {
            $selectedMonthRow = $viewData['selectedMonthRow'];

            fputcsv($handle, ['Monthly Ticket Report']);
            fputcsv($handle, ['Period', $selectedMonthRow['month_label'] ?? $viewData['selectedMonthKey']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Received', $selectedMonthRow['received'] ?? 0]);
            fputcsv($handle, ['Resolved', $selectedMonthRow['resolved'] ?? 0]);
            fputcsv($handle, ['Open at period end', $selectedMonthRow['open_end_of_month'] ?? 0]);
            fputcsv($handle, ['Resolution rate (%)', $selectedMonthRow['resolution_rate'] ?? 0]);
            fputcsv($handle, []);

            fputcsv($handle, ['Status Breakdown']);
            foreach ($viewData['statusBreakdown'] as $status => $count) {
                if ($status === 'resolved_only') {
                    continue;
                }
                fputcsv($handle, [ucfirst(str_replace('_', ' ', (string) $status)), $count]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Priority Breakdown']);
            foreach ($viewData['priorityBreakdown'] as $priority => $count) {
                fputcsv($handle, [ucfirst(str_replace('_', ' ', (string) $priority)), $count]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Category Breakdown']);
            fputcsv($handle, ['Category', 'Count', 'Share (%)']);
            foreach ($viewData['categoryBreakdown'] as $row) {
                fputcsv($handle, [$this->csvCell($row['name']), $row['count'], $row['share']]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $viewData
     */
    private function recordMonthlyExport(Request $request, string $event, string $description, array $viewData): void
    {
        $this->recordExport($request, $event, $description, (int) ($viewData['selectedMonthRow']['received'] ?? 0), [
            'month' => (string) $viewData['selectedMonthKey'],
        ]);
    }
}
