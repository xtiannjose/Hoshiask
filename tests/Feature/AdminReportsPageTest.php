<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AdminReportsPageTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 2, 25, 9, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_super_user_can_open_reports_page_and_view_statistics(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Reports Super', 'reports-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Reports Client', 'reports-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'Requester One',
            'contact_number' => '09180000001',
            'email' => 'requester-one@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Reports open ticket',
            'description' => 'Open ticket for reporting',
            'priority' => 'high',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::create([
            'name' => 'Requester Two',
            'contact_number' => '09180000002',
            'email' => 'requester-two@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Reports resolved ticket',
            'description' => 'Resolved ticket for reporting',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertSee('Reports');
        $response->assertSee('Total Tickets');
        $response->assertSee('Resolution Rate');
        $response->assertSee('Operational KPIs');
        $response->assertSee('Daily Ticket Statistics');
        $response->assertSee('Monthly Performance (Last 12 Months)');
        $response->assertDontSee('Details Filter');
        $response->assertDontSee('Detailed Report Scope');
        $response->assertDontSee('Top Technical Users');
        $response->assertDontSee('Category Breakdown (All Time)');
        $response->assertViewHas('ticketsBreakdownOverview', function (array $overview) {
            return ($overview['label'] ?? null) === 'All Time';
        });
        $response->assertDontSee('% Change Vs Previous Period');
        $response->assertDontSee('SLA Compliance Rate');
        $response->assertDontSee('Backlog (Period End)');
        $response->assertDontSee('Average resolution time');
        $response->assertDontSee('SLA compliance (selection)');
    }

    public function test_reports_page_can_return_partial_html_for_filter_refresh(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Partial Reports Super', 'partial-reports-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Partial Reports Client', 'partial-reports-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'Partial Requester',
            'contact_number' => '09180000003',
            'email' => 'partial-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Reports partial ticket',
            'description' => 'Ticket for partial report rendering',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)
            ->getJson(route('admin.reports.index', [
                'month' => 'all',
                'partial' => 1,
            ]), [
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['html']);
        $this->assertStringContainsString('data-admin-reports-shell', (string) $response->json('html'));
        $this->assertStringContainsString('SLA Overview', (string) $response->json('html'));
    }

    public function test_reports_page_builds_sla_metrics_from_ticket_timelines(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('SLA Super', 'sla-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('SLA Client', 'sla-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $underOneHourTicket = Ticket::create([
            'name' => 'Under One Hour',
            'contact_number' => '09180001001',
            'email' => 'under-one-hour@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Under one hour ticket',
            'description' => 'Should stay inside the acknowledgment window.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($underOneHourTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 25, 8, 40, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 8, 40, 0),
        ]);
        $underOneHourTicket->refresh();
        TicketUserState::markAcknowledged($underOneHourTicket, $superUser->id, Carbon::create(2026, 2, 25, 8, 50, 0));

        $severityOneTicket = Ticket::create([
            'name' => 'Severity One',
            'contact_number' => '09180001002',
            'email' => 'severity-one@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Severity one ticket',
            'description' => 'Should resolve within ninety minutes.',
            'priority' => 'high',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 2, 25, 7, 30, 0),
            'satisfaction_rating' => 5,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($severityOneTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 25, 6, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 7, 30, 0),
        ]);
        $severityOneTicket->refresh();
        TicketUserState::markAcknowledged($severityOneTicket, $superUser->id, Carbon::create(2026, 2, 25, 6, 20, 0));
        $severityOneReply = TicketReply::create([
            'ticket_id' => $severityOneTicket->id,
            'user_id' => $superUser->id,
            'message' => 'We are checking this now.',
            'is_internal' => false,
        ]);
        TicketReply::query()->whereKey($severityOneReply->id)->update([
            'created_at' => Carbon::create(2026, 2, 25, 6, 30, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 6, 30, 0),
        ]);

        $severityTwoTicket = Ticket::create([
            'name' => 'Severity Two',
            'contact_number' => '09180001003',
            'email' => 'severity-two@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Severity two ticket',
            'description' => 'Open for five hours.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($severityTwoTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 25, 4, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 4, 0, 0),
        ]);

        $severityThreeTicket = Ticket::create([
            'name' => 'Severity Three',
            'contact_number' => '09180001004',
            'email' => 'severity-three@example.com',
            'province' => 'NCR',
            'municipality' => 'Quezon City',
            'subject' => 'Severity three ticket',
            'description' => 'Closed after twenty six hours.',
            'priority' => 'high',
            'status' => 'closed',
            'closed_at' => Carbon::create(2026, 2, 25, 8, 0, 0),
            'resolved_at' => Carbon::create(2026, 2, 25, 8, 0, 0),
            'satisfaction_rating' => 3,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($severityThreeTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 24, 6, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 8, 0, 0),
        ]);
        $severityThreeTicket->refresh();
        TicketUserState::markAcknowledged($severityThreeTicket, $superUser->id, Carbon::create(2026, 2, 24, 8, 0, 0));
        $severityThreeReply = TicketReply::create([
            'ticket_id' => $severityThreeTicket->id,
            'user_id' => $superUser->id,
            'message' => 'This is being worked on.',
            'is_internal' => false,
        ]);
        TicketReply::query()->whereKey($severityThreeReply->id)->update([
            'created_at' => Carbon::create(2026, 2, 24, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 24, 9, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
        ]));

        $response->assertOk();
        $response->assertSee('SLA Overview');
        $response->assertSee('Resolution Time Compliance');
        $response->assertViewHas('slaReport', function (array $slaReport) {
            $buckets = collect($slaReport['resolution_buckets'] ?? [])->keyBy('label');

            return ($slaReport['label'] ?? null) === 'Feb 2026'
                && array_keys($slaReport) === ['label', 'resolution_buckets']
                && (int) data_get($buckets, 'Under 1 Hour.count', 0) === 0
                && (float) data_get($buckets, 'Under 1 Hour.rate', 0) === 0.0
                && (int) data_get($buckets, 'Under 4 Hours.count', 0) === 1
                && (float) data_get($buckets, 'Under 4 Hours.rate', 0) === 50.0
                && (int) data_get($buckets, 'Under 24 Hours.count', 0) === 0
                && (float) data_get($buckets, 'Under 24 Hours.rate', 0) === 0.0
                && (int) data_get($buckets, 'Above 24 Hours.count', 0) === 1
                && (float) data_get($buckets, 'Above 24 Hours.rate', 0) === 50.0;
        });
    }

    public function test_reports_page_can_use_all_time_reporting_period(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('All Time Super', 'all-time-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('All Time Client', 'all-time-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $januaryTicket = Ticket::create([
            'name' => 'January Requester',
            'contact_number' => '09180001005',
            'email' => 'january-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'January ticket',
            'description' => 'Should be included in all-time scope.',
            'priority' => 'high',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($januaryTicket->id)->update([
            'created_at' => Carbon::create(2026, 1, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 10, 9, 0, 0),
        ]);

        $februaryTicket = Ticket::create([
            'name' => 'February Requester',
            'contact_number' => '09180001006',
            'email' => 'february-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'February ticket',
            'description' => 'Resolved ticket in all-time scope.',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 2, 20, 14, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($februaryTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 20, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 20, 14, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('All Time');
        $response->assertDontSee('Download Monthly PDF');
        $response->assertViewHas('selectedMonthKey', 'all');
        $response->assertViewHas('selectedMonthIsAllTime', true);
        $response->assertViewHas('periodOverview', function (array $overview) {
            return ($overview['label'] ?? null) === 'All Time'
                && (int) ($overview['total_tickets'] ?? 0) === 2
                && (int) ($overview['resolved'] ?? 0) === 1;
        });
        $response->assertViewHas('selectedMonthRow', function (array $row) {
            return ($row['month_label'] ?? null) === 'All Time'
                && (int) ($row['received'] ?? 0) === 2
                && (int) ($row['resolved'] ?? 0) === 1
                && (float) ($row['resolution_rate'] ?? 0) === 50.0;
        });
        $response->assertViewHas('slaReport', function (array $slaReport) {
            return ($slaReport['label'] ?? null) === 'All Time'
                && collect($slaReport['resolution_buckets'])->sum('count') === 1;
        });
        $response->assertViewHas('monthlyPerformanceFocusMonthKey', '2026-02');
    }

    public function test_reports_exclude_only_staff_to_staff_internal_tickets_and_keep_staff_logged_client_tickets(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Internal Scope Super', 'internal-scope-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Internal Scope Client', 'internal-scope-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $technicalRequester = $this->createUser('Internal Scope Technical', 'internal-scope-technical@example.com', User::ROLE_TECHNICAL);
        $assignedTechnical = $this->createUser('Internal Scope Assignee', 'internal-scope-assignee@example.com', User::ROLE_TECHNICAL);
        $category = $this->createCategory();

        $externalTicket = Ticket::create([
            'name' => 'External Requester',
            'contact_number' => '09180001007',
            'email' => 'external-report-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'External report ticket',
            'description' => 'Should count in reports.',
            'priority' => 'medium',
            'status' => 'resolved',
            'ticket_type' => Ticket::TYPE_EXTERNAL,
            'resolved_at' => Carbon::create(2026, 2, 18, 12, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($externalTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 18, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 18, 12, 0, 0),
        ]);

        $staffLoggedClientTicket = Ticket::create([
            'name' => 'Staff Logged Client Requester',
            'contact_number' => '09180001009',
            'email' => 'staff-logged-client-report-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Staff logged client report ticket',
            'description' => 'Should count in reports even if marked internal.',
            'priority' => 'medium',
            'status' => 'closed',
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'created_by_user_id' => $superUser->id,
            'resolved_at' => Carbon::create(2026, 2, 18, 17, 0, 0),
            'closed_at' => Carbon::create(2026, 2, 18, 17, 0, 0),
            'user_id' => $client->id,
            'assigned_to' => $assignedTechnical->id,
            'assigned_at' => Carbon::create(2026, 2, 18, 9, 15, 0),
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($staffLoggedClientTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 18, 9, 5, 0),
            'updated_at' => Carbon::create(2026, 2, 18, 17, 0, 0),
        ]);

        $internalTicket = Ticket::create([
            'name' => 'Internal Requester',
            'contact_number' => '09180001008',
            'email' => 'internal-report-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Internal report ticket',
            'description' => 'Should stay out of reports.',
            'priority' => 'medium',
            'status' => 'closed',
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'creation_source' => Ticket::CREATION_SOURCE_STAFF_FOR_STAFF,
            'resolved_at' => Carbon::create(2026, 2, 19, 16, 0, 0),
            'closed_at' => Carbon::create(2026, 2, 19, 16, 0, 0),
            'user_id' => $technicalRequester->id,
            'assigned_to' => $assignedTechnical->id,
            'assigned_at' => Carbon::create(2026, 2, 19, 9, 30, 0),
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($internalTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 19, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 19, 16, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
        ]));

        $response->assertOk();
        $response->assertViewHas('stats', function (array $stats) {
            return (int) ($stats['total_tickets'] ?? 0) === 2
                && (int) ($stats['open_tickets'] ?? 0) === 0
                && (int) ($stats['closed_tickets'] ?? 0) === 2
                && (float) ($stats['resolution_rate'] ?? 0) === 100.0;
        });
        $response->assertViewHas('selectedMonthRow', function (array $row) {
            return (int) ($row['received'] ?? 0) === 2
                && (int) ($row['resolved'] ?? 0) === 2
                && (int) ($row['completed_in_period'] ?? 0) === 2
                && (float) ($row['resolution_rate'] ?? 0) === 100.0;
        });
        $response->assertViewHas('slaReport', function (array $slaReport) {
            return collect($slaReport['resolution_buckets'])->sum('count') === 2;
        });
        $response->assertViewHas('ticketsBreakdownOverview', function (array $overview) {
            return (int) ($overview['total_created'] ?? 0) === 2
                && (int) ($overview['closed'] ?? 0) === 1
                && (int) ($overview['resolved'] ?? 0) === 2;
        });
    }

    public function test_reports_page_shows_daily_received_in_progress_and_resolved_statistics_for_selected_date(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Daily Reports Super', 'daily-reports-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Daily Reports Client', 'daily-reports-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $inProgressTicket = Ticket::create([
            'name' => 'In Progress Requester',
            'contact_number' => '09180000011',
            'email' => 'in-progress-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'In progress ticket for daily stats',
            'description' => 'Counts as received and in progress.',
            'priority' => 'high',
            'status' => 'in_progress',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::query()->whereKey($inProgressTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 24, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 24, 10, 0, 0),
        ]);

        $resolvedTicket = Ticket::create([
            'name' => 'Resolved Requester',
            'contact_number' => '09180000012',
            'email' => 'resolved-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Resolved ticket for daily stats',
            'description' => 'Counts as resolved on target day.',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 2, 24, 15, 30, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::query()->whereKey($resolvedTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 20, 8, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 24, 15, 30, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
            'daily_date' => '2026-02-24',
        ]));

        $response->assertOk();
        $response->assertSee('Daily Ticket Statistics');
        $response->assertViewHas('dailySelectedDateValue', '2026-02-24');
        $response->assertViewHas('dailySelectedStats', function (array $dailyStats) {
            return $dailyStats['date'] === '2026-02-24'
                && (int) $dailyStats['received'] === 1
                && (int) $dailyStats['in_progress'] === 1
                && (int) $dailyStats['resolved'] === 0
                && (int) ($dailyStats['closed'] ?? 0) === 0;
        });
    }

    public function test_daily_ticket_statistics_defaults_to_current_month_and_today_when_no_daily_filter_is_set(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Daily Default Super', 'daily-default-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Daily Default Client', 'daily-default-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $todayTicket = Ticket::create([
            'name' => 'Today Requester',
            'contact_number' => '09180000013',
            'email' => 'today-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Ticket for today default',
            'description' => 'Should be counted in default daily view.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($todayTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 25, 11, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 11, 0, 0),
        ]);

        $pastMonthTicket = Ticket::create([
            'name' => 'Past Month Requester',
            'contact_number' => '09180000014',
            'email' => 'past-month-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Ticket for january',
            'description' => 'Should not be counted in default daily view.',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($pastMonthTicket->id)->update([
            'created_at' => Carbon::create(2026, 1, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 10, 9, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-01',
        ]));

        $response->assertOk();
        $response->assertViewHas('dailyMonthKey', '2026-02');
        $response->assertViewHas('dailySelectedDateValue', '2026-02-25');
        $response->assertViewHas('dailySelectedStats', function (array $dailyStats) {
            return $dailyStats['date'] === '2026-02-25'
                && (int) $dailyStats['received'] === 1;
        });
    }

    public function test_daily_ticket_statistics_can_show_month_totals_when_all_days_is_selected(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Daily All Super', 'daily-all-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Daily All Client', 'daily-all-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $inProgressTicket = Ticket::create([
            'name' => 'All Days In Progress',
            'contact_number' => '09180000015',
            'email' => 'all-days-in-progress@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'All days in progress',
            'description' => 'Counted in month received and in progress.',
            'priority' => 'medium',
            'status' => 'in_progress',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($inProgressTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 5, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 5, 10, 0, 0),
        ]);

        $openTicket = Ticket::create([
            'name' => 'All Days Open',
            'contact_number' => '09180000016',
            'email' => 'all-days-open@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'All days open',
            'description' => 'Counted in month received.',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($openTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 18, 11, 30, 0),
            'updated_at' => Carbon::create(2026, 2, 18, 11, 30, 0),
        ]);

        $resolvedTicket = Ticket::create([
            'name' => 'All Days Resolved',
            'contact_number' => '09180000017',
            'email' => 'all-days-resolved@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Resolved in selected month',
            'description' => 'Counts in month resolved total.',
            'priority' => 'high',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 2, 14, 14, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($resolvedTicket->id)->update([
            'created_at' => Carbon::create(2026, 1, 30, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 14, 14, 0, 0),
        ]);

        $closedTicket = Ticket::create([
            'name' => 'All Days Closed',
            'contact_number' => '09180000018',
            'email' => 'all-days-closed@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Closed in selected month',
            'description' => 'Counts in month resolved total via closed_at.',
            'priority' => 'medium',
            'status' => 'closed',
            'resolved_at' => null,
            'closed_at' => Carbon::create(2026, 2, 20, 16, 30, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($closedTicket->id)->update([
            'created_at' => Carbon::create(2026, 1, 20, 8, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 20, 16, 30, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
            'daily_month' => '2026-02',
            'daily_date' => 'all',
        ]));

        $response->assertOk();
        $response->assertViewHas('dailySelectedDateValue', 'all');
        $response->assertViewHas('dailySelectedStats', function (array $dailyStats) {
            return ($dailyStats['mode'] ?? null) === 'month'
                && $dailyStats['date'] === null
                && str_contains((string) ($dailyStats['label'] ?? ''), 'All days in Feb 2026')
                && (int) $dailyStats['received'] === 2
                && (int) $dailyStats['in_progress'] === 1
                && (int) $dailyStats['resolved'] === 0
                && (int) ($dailyStats['closed'] ?? 0) === 0;
        });
    }

    public function test_total_ticket_breakdown_shows_closed_separately_but_keeps_it_in_resolved_count(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Breakdown Super', 'breakdown-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Breakdown Client', 'breakdown-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $closedTicket = Ticket::create([
            'name' => 'Breakdown Closed Requester',
            'contact_number' => '09180000999',
            'email' => 'breakdown-closed@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Closed ticket in month',
            'description' => 'Should appear in resolved/closed report bucket.',
            'priority' => 'medium',
            'status' => 'closed',
            'closed_at' => Carbon::create(2026, 2, 18, 14, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::query()->whereKey($closedTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 18, 14, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
        ]));

        $response->assertOk();
        $response->assertSee('Resolved');
        $response->assertSee('Closed');
        $response->assertViewHas('ticketsBreakdownOverview', function (array $overview) {
            return (int) ($overview['resolved'] ?? 0) === 1
                && (int) ($overview['closed'] ?? 0) === 1;
        });
        $response->assertViewHas('selectedMonthStatuses', function (array $statuses) {
            return (int) ($statuses['resolved'] ?? 0) === 1
                && (int) ($statuses['closed'] ?? 0) === 1;
        });
    }

    public function test_total_ticket_breakdown_slices_add_up_to_the_total_including_cancelled_tickets(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Slice Super', 'slice-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Slice Client', 'slice-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        foreach (['open', 'in_progress', 'pending', 'resolved', 'closed', 'cancelled', 'cancelled'] as $status) {
            $this->createTicket($client, $category, ['status' => $status]);
        }

        $response = $this->actingAs($superUser)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertViewHas('ticketsBreakdownOverview', function (array $overview) {
            return (int) $overview['total_created'] === 7 && (int) $overview['cancelled'] === 2;
        });
        $response->assertViewHas('reportVisuals', function (array $visuals) {
            $slices = collect($visuals['ticket_pie']['slices']);
            $cancelled = $slices->firstWhere('label', 'Cancelled');

            return $cancelled !== null
                && (int) $cancelled['count'] === 2
                && (int) $slices->sum('count') === 7
                && (int) $visuals['ticket_pie']['total'] === 7;
        });
        $response->assertSee('Cancelled');
    }

    public function test_cancelled_tickets_are_left_out_of_the_resolution_rate_backlog_and_sla_measures(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Cancel Rate Super', 'cancel-rate-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Cancel Rate Client', 'cancel-rate-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        // Two resolved (closed), one still open, one withdrawn by the client.
        $this->createTicket($client, $category, ['status' => 'closed', 'closed_at' => now(), 'resolved_at' => now()]);
        $this->createTicket($client, $category, ['status' => 'closed', 'closed_at' => now(), 'resolved_at' => now()]);
        $this->createTicket($client, $category, ['status' => 'open']);
        $this->createTicket($client, $category, ['status' => 'cancelled', 'cancelled_at' => now()]);

        foreach ([now()->format('Y-m'), 'all'] as $month) {
            $response = $this->actingAs($superUser)->get(route('admin.reports.index', ['month' => $month]));

            $response->assertOk();
            // 2 resolved of the 3 that could be resolved (the cancelled one is not counted against the rate).
            $response->assertViewHas('stats', fn (array $stats) => $stats['total_tickets'] === 4
                && (float) $stats['resolution_rate'] === 66.7);
            $response->assertViewHas('selectedMonthRow', fn (array $row) => (int) $row['received'] === 4
                && (float) $row['resolution_rate'] === 66.7
                // Only the open ticket is backlog; the cancelled one is not.
                && (int) $row['open_end_of_month'] === 1);
            // Resolution times cover the 2 completed tickets.
            $response->assertViewHas('slaReport', fn (array $sla) => collect($sla['resolution_buckets'])->sum('count') === 2);
            // The page explains what it leaves out, so totals are not a surprise next to the dashboard.
            $response->assertSee('data-reports-scope-note', false);
            $response->assertSee('Internal staff-to-staff tickets are left out');
            $response->assertSee('Cancelled tickets are not counted.');
        }
    }

    public function test_client_cannot_open_reports_page(): void
    {
        config(['legal.require_acceptance' => false]);

        $client = $this->createUser('Restricted Client', 'restricted-client@example.com', User::ROLE_CLIENT, 'Hoshishi');

        $response = $this->actingAs($client)->get(route('admin.reports.index'));

        $response->assertForbidden();
    }

    public function test_technical_user_cannot_open_reports_page(): void
    {
        config(['legal.require_acceptance' => false]);

        $technical = $this->createUser('Restricted Technical', 'restricted-technical@example.com', User::ROLE_TECHNICAL);

        $response = $this->actingAs($technical)->get(route('admin.reports.index'));

        $response->assertForbidden();
    }

    public function test_super_user_can_download_monthly_pdf_report(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('PDF Super', 'pdf-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('PDF Client', 'pdf-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'PDF Requester',
            'contact_number' => '09180000100',
            'email' => 'pdf-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Monthly PDF ticket',
            'description' => 'Generates data for PDF report',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.monthly.pdf', [
            'month' => now()->format('Y-m'),
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('ticket-monthly-report-', (string) $response->headers->get('content-disposition'));
    }

    public function test_super_user_can_export_monthly_report_as_csv(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('CSV Super', 'csv-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('CSV Client', 'csv-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory('CSV Report Category');

        Ticket::create([
            'name' => 'CSV Requester',
            'contact_number' => '09180000200',
            'email' => 'csv-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Monthly CSV ticket',
            'description' => 'Generates data for CSV export',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.monthly.csv', [
            'month' => now()->format('Y-m'),
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('ticket-monthly-report-', (string) $response->headers->get('content-disposition'));

        $csvBody = $response->streamedContent();
        $this->assertStringContainsString('Monthly Ticket Report', $csvBody);
        $this->assertStringContainsString('Status Breakdown', $csvBody);
        $this->assertStringContainsString('Category Breakdown', $csvBody);
        $this->assertStringContainsString('CSV Report Category', $csvBody);
    }

    public function test_monthly_csv_export_escapes_spreadsheet_formula_values(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('CSV Formula Super', 'csv-formula-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('CSV Formula Client', 'csv-formula-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory('=HYPERLINK("https://example.test")');

        Ticket::create([
            'name' => 'CSV Formula Requester',
            'contact_number' => '09180000201',
            'email' => 'csv-formula-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Monthly CSV formula ticket',
            'description' => 'Generates data for CSV export formula check',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.monthly.csv', [
            'month' => now()->format('Y-m'),
        ]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\R/', trim($csv)) ?: []);

        $formulaRow = collect($rows)->first(fn ($row) => str_contains((string) ($row[0] ?? ''), 'HYPERLINK'));
        $this->assertNotNull($formulaRow);
        $this->assertStringStartsWith("'=", $formulaRow[0]);
    }

    public function test_technician_cannot_export_monthly_csv(): void
    {
        config(['legal.require_acceptance' => false]);

        $technician = $this->createUser('CSV Technician', 'csv-technician@example.com', User::ROLE_TECHNICAL);

        $response = $this->actingAs($technician)->get(route('admin.reports.monthly.csv', [
            'month' => now()->format('Y-m'),
        ]));

        $response->assertForbidden();
    }

    public function test_reports_page_uses_updated_severity_colors(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Severity Viewer', 'severity-viewer@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Severity Client', 'severity-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'Severity One Requester',
            'contact_number' => '09180000111',
            'email' => 'severity-one-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Severity one ticket',
            'description' => 'Should render using the new red severity color.',
            'priority' => 'high',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::create([
            'name' => 'Severity Three Requester',
            'contact_number' => '09180000112',
            'email' => 'severity-three-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Severity three ticket',
            'description' => 'Should render using the new green severity color.',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $severityOneTicket = Ticket::query()->where('subject', 'Severity one ticket')->firstOrFail();
        $severityThreeTicket = Ticket::query()->where('subject', 'Severity three ticket')->firstOrFail();

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('#10b981', false);
        $response->assertSee('#ef4444', false);
        $this->assertSame('bg-red-100 text-red-800', $severityOneTicket->priority_badge_class);
        $this->assertSame('bg-emerald-100 text-emerald-800', $severityThreeTicket->priority_badge_class);
    }

    public function test_monthly_completed_and_open_counts_handle_closed_tickets_correctly(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Metrics Super', 'metrics-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Metrics Client', 'metrics-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'Closed Requester',
            'contact_number' => '09180000200',
            'email' => 'closed-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Closed without resolve timestamp',
            'description' => 'Closed ticket should count as completed and not open.',
            'priority' => 'high',
            'status' => 'closed',
            'resolved_at' => null,
            'closed_at' => now()->subDay(),
            'user_id' => $client->id,
            'category_id' => $category->id,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => now()->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertViewHas('selectedMonthRow', function (array $row) {
            return (int) $row['received'] === 1
                && (int) $row['resolved'] === 1
                && (int) $row['open_end_of_month'] === 0
                && (float) $row['resolution_rate'] === 100.0;
        });
    }

    public function test_monthly_completed_count_ignores_dates_backfilled_on_a_still_open_ticket(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Backfill Super', 'backfill-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Backfill Client', 'backfill-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        // Simulates the "Update Dates" admin action, which can set resolved_at/closed_at
        // for record-keeping without changing status - the monthly report must not treat
        // this ticket as completed while every other view still shows it as open.
        $ticket = $this->createTicket($client, $category, [
            'subject' => 'Backdated but still open',
            'contact_number' => '09180000400',
            'email' => 'backfill-open@example.com',
            'status' => 'open',
            'resolved_at' => now()->subDay(),
            'closed_at' => now()->subDay(),
        ]);
        Ticket::query()->whereKey($ticket->id)->update(['created_at' => now()->subDays(3)]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => now()->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertViewHas('selectedMonthRow', function (array $row) {
            return (int) $row['received'] === 1
                && (int) $row['resolved'] === 0
                && (int) $row['open_end_of_month'] === 1;
        });
    }

    public function test_monthly_backlog_counts_ticket_completion_only_once_across_resolve_and_close_months(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Backlog Super', 'backlog-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Backlog Client', 'backlog-client@example.com', User::ROLE_CLIENT);
        $category = $this->createCategory('Backlog Category');

        // Stays open for the whole window so the running backlog count never clamps to zero,
        // which would otherwise mask the double-subtraction bug below.
        $openTicket = $this->createTicket($client, $category, [
            'subject' => 'Still open ticket',
            'contact_number' => '09180000300',
            'email' => 'backlog-open@example.com',
            'status' => 'open',
        ]);
        Ticket::query()->whereKey($openTicket->id)->update([
            'created_at' => Carbon::create(2025, 10, 15, 9, 0, 0),
            'updated_at' => Carbon::create(2025, 10, 15, 9, 0, 0),
        ]);

        // Resolved in December, formally closed a month later in January - the normal
        // lifecycle path (closing backfills resolved_at only if it wasn't already set).
        $resolvedThenClosedTicket = $this->createTicket($client, $category, [
            'subject' => 'Resolved then closed a month later',
            'contact_number' => '09180000301',
            'email' => 'backlog-resolved-then-closed@example.com',
            'status' => 'closed',
            'resolved_at' => Carbon::create(2025, 12, 10, 9, 0, 0),
            'closed_at' => Carbon::create(2026, 1, 10, 9, 0, 0),
        ]);
        Ticket::query()->whereKey($resolvedThenClosedTicket->id)->update([
            'created_at' => Carbon::create(2025, 11, 15, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 10, 9, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-01',
        ]));

        $response->assertOk();
        $response->assertViewHas('monthlyReportRows', function ($rows) {
            $decemberRow = $rows->firstWhere('month_key', '2025-12');
            $januaryRow = $rows->firstWhere('month_key', '2026-01');

            return $decemberRow
                && $januaryRow
                && (int) $decemberRow['completed_in_period'] === 1
                && (int) $decemberRow['open_end_of_month'] === 1
                && (int) $januaryRow['completed_in_period'] === 0
                && (int) $januaryRow['open_end_of_month'] === 1;
        });
    }

    public function test_monthly_resolution_rate_counts_tickets_completed_after_the_creation_month(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Cohort Super', 'cohort-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Cohort Client', 'cohort-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'name' => 'Cross Month Requester',
            'contact_number' => '09180000201',
            'email' => 'cross-month-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Created in January, resolved in February',
            'description' => 'Should still count toward January completion rate.',
            'priority' => 'medium',
            'status' => 'closed',
            'resolved_at' => Carbon::create(2026, 2, 2, 14, 30, 0),
            'closed_at' => Carbon::create(2026, 2, 2, 14, 30, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::query()->whereKey($ticket->id)->update([
            'created_at' => Carbon::create(2026, 1, 31, 16, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 2, 14, 30, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-01',
        ]));

        $response->assertOk();
        $response->assertViewHas('selectedMonthRow', function (array $row) {
            return (int) $row['received'] === 1
                && (int) $row['resolved'] === 1
                && (int) ($row['completed_in_period'] ?? 0) === 0
                && (int) $row['open_end_of_month'] === 1
                && (float) $row['resolution_rate'] === 100.0;
        });
    }

    public function test_report_mix_drilldowns_default_to_all_time_scope_and_use_all_tickets_tab(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Mix Drilldown Super', 'mix-drilldown-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Mix Drilldown Client', 'mix-drilldown-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'Mix Drilldown Requester',
            'contact_number' => '09180000202',
            'email' => 'mix-drilldown-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Priority and category drilldown',
            'description' => 'Ensures report links keep full created-period scope.',
            'priority' => 'high',
            'status' => 'closed',
            'closed_at' => Carbon::create(2026, 2, 8, 12, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
        ]));

        $response->assertOk();
        $response->assertViewHas('ticketHistoryScope', []);
        $response->assertSee('tab=all&amp;category_bucket=', false);
        $response->assertSee('tab=all&amp;priority=severity_1', false);
        $response->assertDontSee('created_from=', false);
        $response->assertDontSee('created_to=', false);
    }

    public function test_sla_report_uses_configured_thresholds(): void
    {
        config([
            'legal.require_acceptance' => false,
            'hoshiask.sla.resolution_buckets.under_one_hour_minutes' => 120,
        ]);

        $superUser = $this->createUser('Config SLA Super', 'config-sla-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Config SLA Client', 'config-sla-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'name' => 'Config SLA Requester',
            'contact_number' => '09180000203',
            'email' => 'config-sla-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Configurable SLA thresholds',
            'description' => 'Verifies report thresholds read from config.',
            'priority' => 'medium',
            'status' => 'closed',
            'resolved_at' => Carbon::create(2026, 2, 25, 8, 30, 0),
            'closed_at' => Carbon::create(2026, 2, 25, 8, 30, 0),
            'satisfaction_rating' => 4,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::query()->whereKey($ticket->id)->update([
            'created_at' => Carbon::create(2026, 2, 25, 7, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 8, 30, 0),
        ]);
        TicketUserState::markAcknowledged($ticket->refresh(), $superUser->id, Carbon::create(2026, 2, 25, 8, 30, 0));

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
        ]));

        $response->assertOk();
        $response->assertViewHas('slaReport', function (array $slaReport) {
            $buckets = collect($slaReport['resolution_buckets'] ?? [])->keyBy('label');

            return (int) data_get($buckets, 'Under 1 Hour.count', 0) === 1;
        });
    }

    public function test_a_very_long_month_value_does_not_make_a_cache_key_longer_than_the_cache_table_allows(): void
    {
        config(['legal.require_acceptance' => false, 'cache.default' => 'database']);
        $superUser = $this->createUser('Long Month Super', 'long-month-super@example.com', User::ROLE_SUPER_USER);

        $this->actingAs($superUser)
            ->get(route('admin.reports.index', ['month' => str_repeat('9', 400), 'daily_date' => str_repeat('x', 400)]))
            ->assertOk();

        // The cache table's key column holds 255 characters; PostgreSQL refuses a longer key with an error.
        $longestKey = collect(DB::table('cache')->pluck('key'))->map(fn (string $key): int => strlen($key))->max();
        $this->assertLessThanOrEqual(255, $longestKey);
    }
}
