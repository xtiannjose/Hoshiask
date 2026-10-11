<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\Reports\CustomReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class CustomReportTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_admin_can_view_custom_report_summary_grouped_by_status(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $this->createTicket($client, $category, ['status' => 'open']);
        $this->createTicket($client, $category, ['status' => 'open']);
        $this->createTicket($client, $category, ['status' => 'cancelled']);

        $response = $this->actingAs($admin)->get(route('admin.reports.custom.index'));

        $response->assertOk();
        $response->assertViewHas('summaryRows', function ($rows) {
            $open = $rows->firstWhere('label', 'Open');
            $cancelled = $rows->firstWhere('label', 'Cancelled');

            return $open && $open['count'] === 2 && $cancelled && $cancelled['count'] === 1;
        });
    }

    public function test_total_count_matches_summary_rows_without_an_extra_query(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report-total@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $this->createTicket($client, $category, ['status' => 'open']);
        $this->createTicket($client, $category, ['status' => 'closed']);
        $this->createTicket($client, $category, ['status' => 'cancelled']);

        $response = $this->actingAs($admin)->get(route('admin.reports.custom.index'));

        $response->assertOk();
        $response->assertViewHas('totalCount', 3);
    }

    public function test_an_overly_wide_date_range_is_capped(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report-cap@example.com', User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.reports.custom.index', [
            'created_from' => '2000-01-01',
            'created_to' => '2026-12-31',
        ]));

        $response->assertOk();
        $response->assertViewHas('dateRange', function (array $dateRange) {
            return $dateRange['start']->diffInDays($dateRange['end']) <= 731
                && str_contains($dateRange['label'], 'capped');
        });
    }

    public function test_status_filter_narrows_the_report_to_cancelled_tickets(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report2@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $this->createTicket($client, $category, ['status' => 'open']);
        $this->createTicket($client, $category, ['status' => 'cancelled']);

        $response = $this->actingAs($admin)->get(route('admin.reports.custom.index', ['status' => 'cancelled']));

        $response->assertOk();
        $response->assertViewHas('totalCount', 1);
    }

    public function test_detailed_layout_lists_matching_tickets(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report3@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, ['subject' => 'Detailed layout ticket']);

        $response = $this->actingAs($admin)->get(route('admin.reports.custom.index', ['layout' => 'detailed']));

        $response->assertOk();
        $response->assertSee('Detailed layout ticket');
        $response->assertSee($ticket->ticket_number);
    }

    public function test_technician_cannot_access_custom_reports(): void
    {
        $technician = $this->createAssignedSupportUser();

        $this->actingAs($technician)
            ->get(route('admin.reports.custom.index'))
            ->assertForbidden();
    }

    public function test_custom_report_csv_export_downloads(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report-csv@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $this->createTicket($client, $this->createCategory());

        $response = $this->actingAs($admin)->get(route('admin.reports.custom.csv'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_the_detailed_csv_lists_every_ticket_in_id_order_across_more_than_one_page(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report-pages@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();

        $expected = [];
        for ($i = 0; $i < 205; $i++) {
            $expected[] = $this->createTicket($client, $category, ['subject' => 'Page test '.$i])->ticket_number;
        }

        $csv = $this->actingAs($admin)
            ->get(route('admin.reports.custom.csv', ['layout' => 'detailed']))
            ->assertOk()
            ->streamedContent();

        $numbers = [];
        foreach (preg_split('/\R/', trim($csv)) ?: [] as $line) {
            $row = str_getcsv($line);
            if (str_starts_with((string) ($row[1] ?? ''), 'Page test ')) {
                $numbers[] = $row[0];
            }
        }

        $this->assertSame($expected, $numbers);
    }

    public function test_custom_report_csv_escapes_spreadsheet_formula_values(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report-formula@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $this->createTicket($client, $this->createCategory(), [
            'subject' => '=HYPERLINK("https://example.test")',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.custom.csv', ['layout' => 'detailed']));

        $response->assertOk();
        $csv = $response->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\R/', trim($csv)) ?: []);

        $formulaRow = collect($rows)->first(fn ($row) => str_contains((string) ($row[1] ?? ''), 'HYPERLINK'));
        $this->assertNotNull($formulaRow);
        $this->assertStringStartsWith("'=", $formulaRow[1]);
    }

    public function test_custom_report_pdf_export_downloads(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report-pdf@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $this->createTicket($client, $this->createCategory());

        $response = $this->actingAs($admin)->get(route('admin.reports.custom.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_detailed_pdf_lists_only_the_newest_rows_so_it_cannot_run_out_of_memory(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report-pdf-cap@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        for ($i = 0; $i <= CustomReportService::PDF_MAX_ROWS; $i++) {
            $this->createTicket($client, $category);
        }

        // Rendering a full-size PDF takes seconds, so only what the controller hands to it is checked.
        $pdf = Mockery::mock(DomPdf::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('pdf'));
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('admin.reports.custom.pdf', Mockery::on(fn (array $data): bool => $data['detailedRows']->count() === CustomReportService::PDF_MAX_ROWS
                && $data['detailedTotal'] === CustomReportService::PDF_MAX_ROWS + 1))
            ->andReturn($pdf);

        $this->actingAs($admin)->get(route('admin.reports.custom.pdf', ['layout' => 'detailed']))->assertOk();
    }

    public function test_detailed_pdf_says_so_when_the_list_was_shortened(): void
    {
        $client = $this->createClient();
        $rows = collect([$this->createTicket($client, $this->createCategory())]);
        $data = [
            'layout' => 'detailed',
            'groupBy' => 'status',
            'dateRange' => ['from' => '2026-01-01', 'to' => '2026-01-31'],
            'summaryRows' => null,
            'detailedRows' => $rows,
        ];

        $shortened = view('admin.reports.custom.pdf', $data + ['detailedTotal' => 9])->render();
        $complete = view('admin.reports.custom.pdf', $data + ['detailedTotal' => 1])->render();

        $this->assertStringContainsString('Showing the 1 newest of 9 matching tickets', $shortened);
        $this->assertStringNotContainsString('Showing the', $complete);
    }

    public function test_report_page_warns_that_the_pdf_is_limited_only_when_it_is(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report-pdf-hint@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $this->createTicket($client, $category);

        $this->actingAs($admin)->get(route('admin.reports.custom.index', ['layout' => 'detailed']))
            ->assertOk()
            ->assertSee('Total: 1 ticket(s)')
            ->assertDontSee('data-report-pdf-limit', false);

        for ($i = 0; $i < CustomReportService::PDF_MAX_ROWS; $i++) {
            $this->createTicket($client, $category);
        }

        $this->actingAs($admin)->get(route('admin.reports.custom.index', ['layout' => 'detailed']))
            ->assertOk()
            ->assertSee('data-report-pdf-limit', false);
    }

    public function test_internal_staff_tickets_are_left_out_like_on_the_reports_page(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report-scope@example.com', User::ROLE_ADMIN);
        $staffRequester = $this->createAssignedSupportUser('Staff Requester', 'staff-requester-scope@example.com');
        $client = $this->createClient();
        $category = $this->createCategory();
        $this->createTicket($client, $category, ['subject' => 'Client ticket one']);
        $this->createTicket($client, $category, ['subject' => 'Client ticket two']);
        $this->createTicket($staffRequester, $category, [
            'subject' => 'Zebra staff-only request',
            'ticket_type' => 'internal',
            'creation_source' => 'staff_for_staff',
        ]);

        $this->actingAs($admin)->get(route('admin.reports.custom.index'))
            ->assertOk()
            ->assertViewHas('totalCount', 2)
            ->assertSee('Internal staff-to-staff tickets are left out.');

        $this->actingAs($admin)->get(route('admin.reports.custom.index', ['layout' => 'detailed']))
            ->assertOk()
            ->assertViewHas('detailedRows', fn ($rows) => $rows->total() === 2
                && $rows->getCollection()->doesntContain('subject', 'Zebra staff-only request'));

        $csv = $this->actingAs($admin)->get(route('admin.reports.custom.csv', ['layout' => 'detailed']))->streamedContent();
        $this->assertStringContainsString('Client ticket two', $csv);
        $this->assertStringNotContainsString('Zebra staff-only request', $csv);
    }

    public function test_field_office_filter_narrows_the_report(): void
    {
        $admin = $this->createUser('Admin User', 'admin-custom-report-office@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $this->createTicket($client, $category, ['department' => 'Alder']);
        $this->createTicket($client, $category, ['department' => 'Alder']);
        $this->createTicket($client, $category, ['department' => 'Juniper']);

        $this->actingAs($admin)->get(route('admin.reports.custom.index', ['department' => 'Alder']))
            ->assertOk()
            ->assertViewHas('totalCount', 2);
    }
}
