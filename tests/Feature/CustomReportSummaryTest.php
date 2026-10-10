<?php

namespace Tests\Feature;

use App\Enums\RootCauseCategory;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Admin\Reports\CustomReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * The summary is counted by the database. These tests count the same tickets by hand, the way the
 * report used to (load every ticket, group in PHP), and require the same rows.
 */
class CustomReportSummaryTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_every_grouping_matches_counting_the_tickets_by_hand(): void
    {
        $client = $this->createClient();
        $network = $this->createCategory('Network');
        $hardware = $this->createCategory('Hardware');
        $tech = $this->createAssignedSupportUser('Tess Tech', 'tess-tech@example.com');
        $shadow = $this->createUser('Hidden Shadow', 'shadow-summary@example.com', User::ROLE_SHADOW);

        $rows = [
            ['status' => 'open', 'priority' => 'severity_1', 'category' => $network, 'assigned_to' => $tech->id, 'department' => 'Juniper'],
            ['status' => 'open', 'priority' => 'severity_1', 'category' => $network, 'assigned_to' => $tech->id, 'department' => 'Juniper'],
            ['status' => 'closed', 'priority' => 'severity_3', 'category' => $hardware, 'assigned_to' => $shadow->id, 'department' => 'Alder', 'root_cause_category' => RootCauseCategory::cases()[0]->value],
            ['status' => 'closed', 'priority' => null, 'category' => $hardware, 'assigned_to' => null, 'department' => '', 'root_cause_category' => RootCauseCategory::cases()[0]->value],
            ['status' => 'cancelled', 'priority' => 'severity_2', 'category' => $network, 'assigned_to' => null, 'department' => 'Alder'],
            ['status' => 'resolved', 'priority' => null, 'category' => $hardware, 'assigned_to' => $tech->id, 'department' => 'Fjord'],
        ];
        foreach ($rows as $row) {
            $this->createTicket($client, $row['category'], collect($row)->except('category')->all());
        }

        $service = app(CustomReportService::class);

        foreach (CustomReportService::GROUP_BY_OPTIONS as $groupBy) {
            $this->assertSame(
                $this->countByHand($groupBy),
                $service->buildSummaryRows(Ticket::query(), $groupBy)->pluck('count', 'label')->all(),
                "grouped by {$groupBy}"
            );
        }

        $labels = $service->buildSummaryRows(Ticket::query(), 'assignee')->pluck('label')->all();
        $this->assertContains(User::supportTeamName(), $labels, 'the shadow account shows as the support team');
        $this->assertNotContains('Hidden Shadow', $labels, 'the shadow account never shows under its own name');
        $this->assertContains('Unassigned', $labels);
    }

    public function test_shares_add_up_and_follow_the_counts(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        foreach (['open', 'open', 'open', 'closed'] as $status) {
            $this->createTicket($client, $category, ['status' => $status]);
        }

        $rows = app(CustomReportService::class)->buildSummaryRows(Ticket::query(), 'status');

        $this->assertSame([['Open', 3, 75.0], ['Closed', 1, 25.0]], $rows->map(fn ($row) => [$row['label'], $row['count'], $row['share']])->all());
    }

    public function test_an_empty_report_has_no_rows(): void
    {
        $this->assertTrue(app(CustomReportService::class)->buildSummaryRows(Ticket::query(), 'category')->isEmpty());
    }

    public function test_the_summary_is_counted_in_the_database_not_by_loading_tickets(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        for ($i = 0; $i < 30; $i++) {
            $this->createTicket($client, $category, ['status' => $i % 2 === 0 ? 'open' : 'closed']);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(CustomReportService::class)->buildSummaryRows(Ticket::query(), 'category');
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, $queries->count(), 'one grouped count and one name lookup');
        $this->assertTrue($queries->contains(fn (string $sql) => str_contains(strtolower($sql), 'group by')));
        $this->assertFalse($queries->contains(fn (string $sql) => str_starts_with(strtolower($sql), 'select * from "tickets"')), 'no ticket is loaded');
    }

    /**
     * The old way: load the tickets, group them in PHP, order by count then label.
     *
     * @return array<string, int>
     */
    private function countByHand(string $groupBy): array
    {
        $tickets = Ticket::query()->with(['category', 'assignedUser'])->get();

        $groups = match ($groupBy) {
            'priority' => $tickets->groupBy(fn (Ticket $ticket) => $ticket->priority ?? '__pending__'),
            'category' => $tickets->groupBy(fn (Ticket $ticket) => $ticket->category->name),
            'root_cause' => $tickets->groupBy(fn (Ticket $ticket) => $ticket->root_cause_category ?? '__none__'),
            'assignee' => $tickets->groupBy(fn (Ticket $ticket) => $ticket->assignedUser?->publicDisplayName() ?? 'Unassigned'),
            'department' => $tickets->groupBy(fn (Ticket $ticket) => $ticket->department ?: 'Unknown'),
            default => $tickets->groupBy(fn (Ticket $ticket) => $ticket->status),
        };

        return $groups
            ->map(fn (Collection $group, string $key) => ['label' => $this->label($groupBy, $key), 'count' => $group->count()])
            ->sortBy([['count', 'desc'], ['label', 'asc']])
            ->pluck('count', 'label')
            ->all();
    }

    private function label(string $groupBy, string $key): string
    {
        return match ($groupBy) {
            'status' => TicketStatus::tryFrom($key)?->label() ?? ucfirst($key),
            'priority' => $key === '__pending__' ? 'Severity not set' : ucfirst(str_replace('_', ' ', $key)),
            'root_cause' => $key === '__none__' ? 'Not specified' : (RootCauseCategory::tryFrom($key)?->label() ?? ucfirst($key)),
            default => $key,
        };
    }
}
