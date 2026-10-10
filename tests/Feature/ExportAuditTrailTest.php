<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSystemLogsUnlocked;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Admin\Reports\CustomReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Every download of ticket data (the ticket list, the monthly report and the custom report) leaves one
 * audit entry saying who asked, with which filters and for how many rows, and never holds personal text.
 */
class ExportAuditTrailTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false]);
    }

    public function test_the_ticket_list_csv_is_recorded_with_its_filters_and_row_count(): void
    {
        $admin = $this->createUser('Audit Admin', 'audit-admin@example.com', User::ROLE_ADMIN);
        $client = $this->createClient('Secret Client Name', 'secret-client@example.com');
        $category = $this->createCategory();
        $this->createTicket($client, $category, ['priority' => 'severity_1', 'subject' => 'Confidential printer story']);
        $this->createTicket($client, $category, ['priority' => 'severity_1', 'subject' => 'Another confidential story']);
        $this->createTicket($client, $category, ['priority' => 'severity_3', 'subject' => 'Not part of the export']);

        $this->actingAs($admin)
            ->get(route('admin.tickets.export', ['tab' => 'tickets', 'priority' => 'severity_1']))
            ->assertOk();

        $entry = $this->onlyEntry('export.tickets_csv');
        $this->assertSame('export', $entry->category);
        $this->assertSame($admin->id, $entry->actor_user_id);
        $this->assertSame(2, $entry->metadata['rows']);
        $this->assertSame('severity_1', $entry->metadata['filters']['priority']);
        $this->assertSame('tickets', $entry->metadata['filters']['tab']);
        $this->assertHoldsNoPersonalText($entry, ['Secret Client Name', 'secret-client@example.com', 'Confidential printer story', 'Another confidential story']);
    }

    public function test_the_export_is_recorded_before_the_file_is_read_and_only_once(): void
    {
        $admin = $this->createUser('Once Admin', 'once-admin@example.com', User::ROLE_ADMIN);
        $this->createTicket($this->createClient(), $this->createCategory());

        $response = $this->actingAs($admin)->get(route('admin.tickets.export', ['tab' => 'tickets']));

        $this->assertSame(1, SystemLog::query()->where('event_type', 'export.tickets_csv')->count(), 'recorded before the body is streamed');
        $response->streamedContent();
        $this->assertSame(1, SystemLog::query()->where('event_type', 'export.tickets_csv')->count());
    }

    public function test_typed_search_text_is_not_recorded_and_page_controls_are_not_filters(): void
    {
        $admin = $this->createUser('Search Admin', 'search-admin@example.com', User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get(route('admin.tickets.export', ['tab' => 'tickets', 'search' => 'maria.santos@example.com', 'page' => 4, 'per_page' => 50, 'sort' => 'status']))
            ->assertOk();

        $entry = $this->onlyEntry('export.tickets_csv');
        $filters = $entry->metadata['filters'];
        $this->assertSame('(typed text, not recorded)', $filters['search']);
        $this->assertHoldsNoPersonalText($entry, ['maria.santos@example.com']);
        $this->assertArrayNotHasKey('page', $filters);
        $this->assertArrayNotHasKey('per_page', $filters);
        $this->assertArrayNotHasKey('sort', $filters);
    }

    public function test_exporting_the_system_logs_does_not_record_the_typed_search_text(): void
    {
        $shadow = $this->createUser('Log Export Shadow', 'log-export-shadow@example.com', User::ROLE_SHADOW);
        $unlocked = [EnsureSystemLogsUnlocked::SESSION_KEY => now()->addHour()->toIso8601String()];

        $this->actingAs($shadow)->withSession($unlocked)
            ->get(route('admin.system-logs.export', ['search' => 'juan.delacruz@example.com', 'category' => 'auth']))
            ->assertOk();

        $entry = $this->onlyEntry('system_logs.exported');
        $this->assertSame('(typed text, not recorded)', $entry->metadata['filters']['search']);
        $this->assertSame('auth', $entry->metadata['filters']['category']);
        $this->assertHoldsNoPersonalText($entry, ['juan.delacruz@example.com']);
    }

    public function test_the_monthly_report_csv_and_pdf_are_each_recorded_with_the_month(): void
    {
        $admin = $this->createUser('Monthly Admin', 'monthly-admin@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $this->createTicket($client, $category);
        $this->createTicket($client, $category);
        $month = now()->format('Y-m');

        $this->actingAs($admin)->get(route('admin.reports.monthly.csv', ['month' => $month]))->assertOk();
        $this->actingAs($admin)->get(route('admin.reports.monthly.pdf', ['month' => $month]))->assertOk();

        foreach (['export.report_monthly_csv', 'export.report_monthly_pdf'] as $event) {
            $entry = $this->onlyEntry($event);
            $this->assertSame('export', $entry->category);
            $this->assertSame($month, $entry->metadata['month']);
            $this->assertSame(2, $entry->metadata['rows']);
            $this->assertSame($month, $entry->metadata['filters']['month']);
        }
    }

    public function test_the_custom_report_csv_and_pdf_are_recorded_with_layout_and_grouping(): void
    {
        $admin = $this->createUser('Custom Admin', 'custom-admin@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $this->createTicket($client, $category);
        $this->createTicket($client, $category);
        $this->createTicket($client, $category);

        $this->actingAs($admin)->get(route('admin.reports.custom.csv', ['layout' => 'summary', 'group_by' => 'category']))->assertOk();
        $this->actingAs($admin)->get(route('admin.reports.custom.pdf', ['layout' => 'detailed']))->assertOk();

        $csv = $this->onlyEntry('export.report_custom_csv');
        $this->assertSame(3, $csv->metadata['rows']);
        $this->assertSame('summary', $csv->metadata['layout']);
        $this->assertSame('category', $csv->metadata['group_by']);

        $pdf = $this->onlyEntry('export.report_custom_pdf');
        $this->assertSame(3, $pdf->metadata['rows']);
        $this->assertSame(3, $pdf->metadata['rows_shown']);
        $this->assertSame('detailed', $pdf->metadata['layout']);
    }

    public function test_the_custom_report_pdf_records_both_the_total_and_the_capped_number_shown(): void
    {
        $admin = $this->createUser('Cap Admin', 'cap-admin@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $total = CustomReportService::PDF_MAX_ROWS + 3;
        for ($i = 0; $i < $total; $i++) {
            $this->createTicket($client, $category);
        }

        // Rendering a full-size PDF needs more memory than a test run has, and only the audit entry is under test.
        $pdf = Mockery::mock(DomPdf::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('pdf'));
        Pdf::shouldReceive('loadView')->once()->andReturn($pdf);

        $this->actingAs($admin)->get(route('admin.reports.custom.pdf', ['layout' => 'detailed']))->assertOk();

        $entry = $this->onlyEntry('export.report_custom_pdf');
        $this->assertSame($total, $entry->metadata['rows']);
        $this->assertSame(CustomReportService::PDF_MAX_ROWS, $entry->metadata['rows_shown']);
    }

    public function test_a_refused_export_writes_no_entry(): void
    {
        $technician = $this->createAssignedSupportUser();
        $client = $this->createClient();

        $this->actingAs($technician)->get(route('admin.reports.monthly.csv'))->assertForbidden();
        $this->actingAs($technician)->get(route('admin.reports.custom.csv'))->assertForbidden();
        $this->actingAs($technician)->get(route('admin.reports.custom.pdf'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.tickets.export'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.reports.monthly.pdf'))->assertForbidden();

        $this->assertSame(0, SystemLog::query()->where('category', 'export')->count());
    }

    public function test_a_technician_ticket_export_is_recorded_with_the_rows_they_could_see(): void
    {
        $technician = $this->createAssignedSupportUser();
        $client = $this->createClient();
        $category = $this->createCategory();
        $mine = $this->createTicket($client, $category);
        $mine->assignedUsers()->syncWithoutDetaching([$technician->id]);
        $this->createTicket($client, $category);

        $this->actingAs($technician)->get(route('admin.tickets.export', ['tab' => 'tickets']))->assertOk();

        $this->assertSame(1, $this->onlyEntry('export.tickets_csv')->metadata['rows']);
    }

    public function test_the_system_logs_page_lists_and_filters_the_export_events(): void
    {
        $shadow = $this->createUser('Audit Shadow', 'audit-shadow@example.com', User::ROLE_SHADOW);
        $admin = $this->createUser('Filter Admin', 'filter-admin@example.com', User::ROLE_ADMIN);
        $this->createTicket($this->createClient(), $this->createCategory());
        $this->actingAs($admin)->get(route('admin.tickets.export', ['tab' => 'tickets']))->assertOk();
        $this->actingAs($admin)->get(route('admin.reports.custom.csv'))->assertOk();

        $unlocked = [EnsureSystemLogsUnlocked::SESSION_KEY => now()->addHour()->toIso8601String()];
        $page = $this->actingAs($shadow)->withSession($unlocked)->get(route('admin.system-logs.index', ['category' => 'export']));

        $page->assertOk()
            ->assertSee('export.tickets_csv')
            ->assertSee('export.report_custom_csv')
            ->assertSee('Exported the ticket list to CSV.');

        $onlyOne = $this->actingAs($shadow)->withSession($unlocked)->get(route('admin.system-logs.index', ['event' => 'export.report_custom_csv']));
        $onlyOne->assertOk()->assertSee('Exported a custom report to CSV.')->assertDontSee('Exported the ticket list to CSV.');

        $this->actingAs($shadow)->withSession($unlocked)->get(route('admin.system-logs.export', ['category' => 'export']))->assertOk();
    }

    private function onlyEntry(string $event): SystemLog
    {
        $entries = SystemLog::query()->where('event_type', $event)->get();
        $this->assertCount(1, $entries, "expected exactly one '{$event}' entry");

        return $entries->first();
    }

    /**
     * @param  list<string>  $forbidden
     */
    private function assertHoldsNoPersonalText(SystemLog $entry, array $forbidden): void
    {
        $everything = json_encode([$entry->description, $entry->metadata]);
        foreach ($forbidden as $text) {
            $this->assertStringNotContainsString($text, (string) $everything);
        }
    }
}
