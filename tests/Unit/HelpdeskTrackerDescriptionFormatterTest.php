<?php

namespace Tests\Unit;

use App\Services\TicketImport\HelpdeskTrackerDescriptionFormatter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HelpdeskTrackerDescriptionFormatterTest extends TestCase
{
    public function test_it_formats_excel_serial_dates_and_times_without_1899_time_artifacts(): void
    {
        $formatter = new HelpdeskTrackerDescriptionFormatter;

        $this->assertSame('03/17/2026', $formatter->formatExcelDate(46098.0));
        $this->assertSame('2:00:00 PM', $formatter->formatExcelTime(0.5833333333333334));
        $this->assertSame('03/17/2026 5:00:00 PM', $formatter->formatExcelDateTime(46098.7083333333));
    }

    public function test_it_builds_the_expected_ticket_information_block(): void
    {
        $formatter = new HelpdeskTrackerDescriptionFormatter;
        $createdAt = Carbon::parse('2026-03-17 14:00:00', 'Asia/Manila')->utc();
        $completedAt = Carbon::parse('2026-03-17 17:00:00', 'Asia/Manila')->utc();

        $description = $formatter->buildDescription([
            'date_received' => '03/17/2026',
            'time_received' => '2:00:00 PM',
            'date_resolved' => '03/17/2026',
            'time_resolved' => '5:00:00 PM',
            'issue_via' => 'via viber call',
            'requestor_details' => null,
            'project_name' => 'Sample Client',
            'issue_description' => 'Set up Access point for starlink',
            'resolution' => 'Assist personel for troubleshooting',
            'attended_by' => 'John Arnold Carrasco',
        ], $createdAt, $completedAt, 'Asia/Manila');

        $this->assertSame(implode(PHP_EOL, [
            'Date Received: 03/17/2026',
            'Time Received: 2:00:00 PM',
            'Date Resolved: 03/17/2026',
            'Time Resolved: 5:00:00 PM',
            'Issue Via: via viber call',
            'Project Name: Sample Client',
            'Issue Description: Set up Access point for starlink',
            'Resolution: Assist personel for troubleshooting',
            'Attended By: John Arnold Carrasco',
            'Created At: 03/17/2026 2:00:00 PM',
            'Completed At: 03/17/2026 5:00:00 PM',
        ]), $description);
    }

    public function test_it_falls_back_to_issue_via_when_issue_description_is_blank_for_subjects(): void
    {
        $formatter = new HelpdeskTrackerDescriptionFormatter;

        $this->assertSame(
            'ICTSI - Server 48 Stopped',
            $formatter->buildSubject('ICTSI', null, 'Server 48 Stopped')
        );
    }

    public function test_it_normalizes_noon_tokens_inside_free_text_fields_without_changing_names(): void
    {
        $formatter = new HelpdeskTrackerDescriptionFormatter;
        $createdAt = Carbon::parse('2025-11-08 08:00:00', 'Asia/Manila')->utc();

        $description = $formatter->buildDescription([
            'date_received' => '11/08/2025',
            'time_received' => '8:00:00 AM',
            'date_resolved' => '11/08/2025',
            'time_resolved' => '12:10 NN',
            'issue_via' => 'Phone',
            'requestor_details' => null,
            'project_name' => 'MNHPI',
            'issue_description' => 'Reported Radius Link down',
            'resolution' => 'Recovered around 12:10 NN after provider update',
            'attended_by' => 'Support Team',
        ], $createdAt, null, 'Asia/Manila');

        $this->assertStringContainsString('Time Resolved: 12:10 PM', $description);
        $this->assertStringContainsString('Resolution: Recovered around 12:10 PM after provider update', $description);
        $this->assertStringContainsString('Project Name: MNHPI', $description);
        $this->assertStringNotContainsString('Requestor Details:', $description);
    }

    public function test_it_preserves_source_time_display_without_forcing_seconds(): void
    {
        $formatter = new HelpdeskTrackerDescriptionFormatter;
        $createdAt = Carbon::parse('2026-03-06 14:00:00', 'Asia/Manila')->utc();
        $completedAt = Carbon::parse('2026-03-06 15:00:00', 'Asia/Manila')->utc();

        $description = $formatter->buildDescription([
            'date_received' => '03/06/2026',
            'time_received' => '2:00:00 PM',
            'date_resolved' => '03/06/2026',
            'time_resolved' => '3:00 PM',
            'issue_via' => 'via online messaging',
            'requestor_details' => null,
            'project_name' => 'Birch MCC Starlink',
            'issue_description' => 'Activation of Starlink',
            'resolution' => 'Activated of starlink Birch Calabarzon Enterprise',
            'attended_by' => 'John Arnold Carrasco',
        ], $createdAt, $completedAt, 'Asia/Manila');

        $this->assertStringContainsString('Time Received: 2:00:00 PM', $description);
        $this->assertStringContainsString('Time Resolved: 3:00 PM', $description);
        $this->assertStringContainsString('Completed At: 03/06/2026 3:00:00 PM', $description);
    }
}
