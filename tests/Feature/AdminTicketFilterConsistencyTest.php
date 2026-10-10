<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AdminTicketFilterConsistencyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_admin_tickets_tab_hides_resolved_and_closed_statuses_and_results(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Status Filter Client', 'status-filter-client@example.com');

        $resolvedTicket = Ticket::create([
            'name' => 'Resolved Requester',
            'contact_number' => '09110000101',
            'email' => 'resolved-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Resolved ticket',
            'description' => 'Resolved issue',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $closedTicket = Ticket::create([
            'name' => 'Closed Requester',
            'contact_number' => '09110000102',
            'email' => 'closed-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Closed ticket',
            'description' => 'Closed issue',
            'priority' => 'high',
            'status' => 'closed',
            'resolved_at' => now()->subMinute(),
            'closed_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $openTicket = Ticket::create([
            'name' => 'Open Requester',
            'contact_number' => '09110000103',
            'email' => 'open-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Open ticket',
            'description' => 'Open issue',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $ticketsResponse = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
        ]));

        $ticketsResponse->assertOk();
        $ticketsResponse->assertSee(route('admin.tickets.show', $openTicket), false);
        $ticketsResponse->assertDontSee(route('admin.tickets.show', $resolvedTicket), false);
        $ticketsResponse->assertDontSee(route('admin.tickets.show', $closedTicket), false);

        $invalidStatusResponse = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'status' => 'resolved',
        ]));

        $invalidStatusResponse->assertOk();
        $invalidStatusResponse->assertSee(route('admin.tickets.show', $openTicket), false);
        $invalidStatusResponse->assertDontSee(route('admin.tickets.show', $resolvedTicket), false);
        $invalidStatusResponse->assertDontSee(route('admin.tickets.show', $closedTicket), false);

        $historyResponse = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'history',
        ]));

        $historyResponse->assertOk();
        $historyResponse->assertSee(route('admin.tickets.show', $resolvedTicket), false);
        $historyResponse->assertSee(route('admin.tickets.show', $closedTicket), false);
        $historyResponse->assertDontSee(route('admin.tickets.show', $openTicket), false);
    }

    public function test_admin_ticket_filter_by_province_uses_ticket_location_fields(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();

        $rizalClient = $this->createClient('Rizal Client', 'rizal-client@example.com');
        $cebuClient = $this->createClient('Cebu Client', 'cebu-client@example.com');

        $rizalTicket = Ticket::create([
            'name' => 'Rizal Requester',
            'contact_number' => '09110000001',
            'email' => 'rizal-requester@example.com',
            'province' => 'Rizal',
            'municipality' => 'Antipolo',
            'subject' => 'Rizal-only ticket',
            'description' => 'Rizal issue',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $rizalClient->id,
            'category_id' => $category->id,
        ]);

        $cebuTicket = Ticket::create([
            'name' => 'Cebu Requester',
            'contact_number' => '09110000002',
            'email' => 'cebu-requester@example.com',
            'province' => 'Cebu',
            'municipality' => 'Cebu City',
            'subject' => 'Cebu-only ticket',
            'description' => 'Cebu issue',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $cebuClient->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'province' => 'Rizal',
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $rizalTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $cebuTicket), false);
    }

    public function test_admin_ticket_filter_by_account_id_uses_exact_user_id(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();

        $firstClient = $this->createClient('Same Name', 'same-name-1@example.com');
        $secondClient = $this->createClient('Same Name', 'same-name-2@example.com');

        $firstTicket = Ticket::create([
            'name' => 'Requester One',
            'contact_number' => '09110000003',
            'email' => 'requester-one@example.com',
            'province' => 'Laguna',
            'municipality' => 'Calamba',
            'subject' => 'Ticket for first duplicate name',
            'description' => 'First account ticket',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $firstClient->id,
            'category_id' => $category->id,
        ]);

        $secondTicket = Ticket::create([
            'name' => 'Requester Two',
            'contact_number' => '09110000004',
            'email' => 'requester-two@example.com',
            'province' => 'Laguna',
            'municipality' => 'Calamba',
            'subject' => 'Ticket for second duplicate name',
            'description' => 'Second account ticket',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $secondClient->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'account_id' => $secondClient->id,
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $secondTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $firstTicket), false);
    }

    public function test_admin_ticket_filter_by_municipality_uses_ticket_location_fields(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();

        $client = $this->createClient('Municipality Client', 'municipality-client@example.com');

        $antipoloTicket = Ticket::create([
            'name' => 'Antipolo Requester',
            'contact_number' => '09110000005',
            'email' => 'antipolo-requester@example.com',
            'province' => 'Rizal',
            'municipality' => 'Antipolo',
            'subject' => 'Antipolo ticket',
            'description' => 'Antipolo issue',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $marikinaTicket = Ticket::create([
            'name' => 'Marikina Requester',
            'contact_number' => '09110000006',
            'email' => 'marikina-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Marikina',
            'subject' => 'Marikina ticket',
            'description' => 'Marikina issue',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'municipality' => 'Antipolo',
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $antipoloTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $marikinaTicket), false);
    }

    public function test_admin_ticket_filter_by_created_date_range_matches_report_drill_down_scope(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Date Range Client', 'date-range-client@example.com');

        $matchingTicket = Ticket::create([
            'name' => 'Matching Requester',
            'contact_number' => '09110000111',
            'email' => 'matching-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Matching date scope',
            'description' => 'Ticket in requested report scope.',
            'priority' => 'high',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($matchingTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 12, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 12, 9, 0, 0),
        ]);

        $outsideTicket = Ticket::create([
            'name' => 'Outside Requester',
            'contact_number' => '09110000112',
            'email' => 'outside-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Outside date scope',
            'description' => 'Ticket out of requested report scope.',
            'priority' => 'high',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($outsideTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 18, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 18, 9, 0, 0),
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'history',
            'status' => 'resolved',
            'created_from' => '2026-02-12',
            'created_to' => '2026-02-12',
            'report_scope' => 'Feb 12, 2026',
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $matchingTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $outsideTicket), false);
    }

    public function test_admin_ticket_filter_by_month_scopes_to_created_month(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Month Filter Client', 'month-filter-client@example.com');

        $marchTicket = Ticket::create([
            'name' => 'March Requester',
            'contact_number' => '09110000121',
            'email' => 'march-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'March-scoped ticket',
            'description' => 'Created in March.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($marchTicket->id)->update([
            'created_at' => Carbon::create(2026, 3, 5, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 3, 5, 9, 0, 0),
        ]);

        $februaryTicket = Ticket::create([
            'name' => 'February Requester',
            'contact_number' => '09110000122',
            'email' => 'february-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'February-scoped ticket',
            'description' => 'Created in February.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($februaryTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 20, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 20, 9, 0, 0),
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'month' => '2026-03',
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $marchTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $februaryTicket), false);
    }

    public function test_admin_ticket_filter_by_assigned_user_matches_ticket_assignment(): void
    {
        $supportUser = $this->createSupportUser();
        $assignedUser = $this->createAssignedSupportUser('Assigned Support One', 'assigned-support-one@example.com');
        $otherAssignedUser = $this->createAssignedSupportUser('Assigned Support Two', 'assigned-support-two@example.com');
        $category = $this->createCategory();
        $client = $this->createClient('Assigned Filter Client', 'assigned-filter-client@example.com');

        $matchingTicket = Ticket::create([
            'name' => 'Assigned Requester',
            'contact_number' => '09110000131',
            'email' => 'assigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Assigned to matching user',
            'description' => 'Assigned to matching support user.',
            'priority' => 'medium',
            'status' => 'in_progress',
            'assigned_to' => $assignedUser->id,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $otherTicket = Ticket::create([
            'name' => 'Other Assigned Requester',
            'contact_number' => '09110000132',
            'email' => 'other-assigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Assigned to a different user',
            'description' => 'Assigned to other support user.',
            'priority' => 'medium',
            'status' => 'in_progress',
            'assigned_to' => $otherAssignedUser->id,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'assigned_to' => $assignedUser->id,
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $matchingTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $otherTicket), false);
    }

    public function test_unassigned_filter_does_not_treat_primary_assignee_without_pivot_row_as_unassigned(): void
    {
        $supportUser = $this->createSupportUser();
        $assignedUser = $this->createAssignedSupportUser('Stale Primary Support', 'stale-primary-support@example.com');
        $category = $this->createCategory();
        $client = $this->createClient('Stale Assignment Client', 'stale-assignment-client@example.com');

        $staleAssignedTicket = Ticket::create([
            'name' => 'Stale Assigned Requester',
            'contact_number' => '09110000133',
            'email' => 'stale-assigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Primary assignee only ticket',
            'description' => 'Should still count as assigned.',
            'priority' => 'medium',
            'status' => 'in_progress',
            'assigned_to' => $assignedUser->id,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        $staleAssignedTicket->assignedUsers()->sync([]);

        $trulyUnassignedTicket = Ticket::create([
            'name' => 'Truly Unassigned Requester',
            'contact_number' => '09110000134',
            'email' => 'truly-unassigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Actually unassigned ticket',
            'description' => 'Should be the only result in the unassigned filter.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'assigned_to' => '0',
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $trulyUnassignedTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $staleAssignedTicket), false);
    }

    public function test_admin_ticket_partial_filter_response_returns_rendered_results_html(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Partial Filter Client', 'partial-filter-client@example.com');

        $marchTicket = Ticket::create([
            'name' => 'Partial March Requester',
            'contact_number' => '09110000141',
            'email' => 'partial-march-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Partial March ticket',
            'description' => 'Included in partial response.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($marchTicket->id)->update([
            'created_at' => Carbon::create(2026, 3, 7, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 3, 7, 9, 0, 0),
        ]);

        $februaryTicket = Ticket::create([
            'name' => 'Partial February Requester',
            'contact_number' => '09110000142',
            'email' => 'partial-february-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Partial February ticket',
            'description' => 'Excluded from partial response.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($februaryTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 7, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 7, 9, 0, 0),
        ]);

        $response = $this->actingAs($supportUser)->getJson(route('admin.tickets.index', [
            'tab' => 'tickets',
            'month' => '2026-03',
            'partial' => '1',
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['html', 'token', 'page_token']);

        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertStringContainsString(route('admin.tickets.show', $marchTicket, false), $payload['html']);
        $this->assertStringNotContainsString(route('admin.tickets.show', $februaryTicket, false), $payload['html']);
        $this->assertStringContainsString('data-admin-tickets-results', $payload['html']);
    }

    public function test_history_filter_options_only_show_applicable_ticket_entries(): void
    {
        $supportUser = $this->createSupportUser();
        $historyCategory = Category::create([
            'name' => 'History Only Category',
            'description' => 'Closed history category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);
        $openCategory = Category::create([
            'name' => 'Open Only Category',
            'description' => 'Open ticket category',
            'color' => '#f97316',
            'is_active' => true,
        ]);
        $historyClient = $this->createClient('History Client', 'history-client@example.com');
        $openClient = $this->createClient('Open Client', 'open-client@example.com');

        $closedTicket = Ticket::create([
            'name' => 'History Requester',
            'contact_number' => '09110000151',
            'email' => 'history-requester@example.com',
            'province' => 'History Province',
            'municipality' => 'History Town',
            'subject' => 'Closed history ticket',
            'description' => 'Visible in history.',
            'priority' => 'medium',
            'status' => 'closed',
            'resolved_at' => now()->subDay(),
            'closed_at' => now(),
            'user_id' => $historyClient->id,
            'category_id' => $historyCategory->id,
        ]);
        Ticket::query()->whereKey($closedTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 10, 9, 0, 0),
        ]);

        $openTicket = Ticket::create([
            'name' => 'Open Requester',
            'contact_number' => '09110000152',
            'email' => 'open-requester@example.com',
            'province' => 'Open Province',
            'municipality' => 'Open Town',
            'subject' => 'Open active ticket',
            'description' => 'Not visible in history.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $openClient->id,
            'category_id' => $openCategory->id,
        ]);
        Ticket::query()->whereKey($openTicket->id)->update([
            'created_at' => Carbon::create(2026, 1, 12, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 12, 9, 0, 0),
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'history',
        ]));

        $response->assertOk();
        $response->assertSee('<option value="'.$historyCategory->id.'"', false);
        $response->assertDontSee('<option value="'.$openCategory->id.'"', false);
        $response->assertSee('<option value="'.$historyClient->id.'"', false);
        $response->assertDontSee('<option value="'.$openClient->id.'"', false);
        $response->assertSee('<option value="History Province"', false);
        $response->assertDontSee('<option value="Open Province"', false);
        $response->assertSee('<option value="History Town"', false);
        $response->assertDontSee('<option value="Open Town"', false);
        $response->assertSee('<option value="2026-02"', false);
        $response->assertDontSee('<option value="2026-01"', false);
    }

    public function test_history_ticket_type_filter_separates_client_and_staff_tickets(): void
    {
        $supportUser = $this->createSupportUser();
        $staffCreator = $this->createAssignedSupportUser('History Staff Creator Filter', 'history-staff-creator-filter@example.com');
        $staffRequester = $this->createAssignedSupportUser('History Staff Requester', 'history-staff-requester@example.com');
        $staffAssignee = $this->createAssignedSupportUser('History Staff Assignee', 'history-staff-assignee@example.com');
        $category = $this->createCategory();
        $client = $this->createClient('History External Client', 'history-external-client@example.com');

        $clientHistoryTicket = Ticket::create([
            'name' => 'Client History Requester',
            'contact_number' => '09110000153',
            'email' => 'client-history-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Closed client history ticket',
            'description' => 'Client ticket in history.',
            'priority' => 'medium',
            'status' => 'closed',
            'ticket_type' => Ticket::TYPE_EXTERNAL,
            'resolved_at' => now()->subDay(),
            'closed_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $staffLoggedClientHistoryTicket = Ticket::create([
            'name' => 'Staff Logged Client Requester',
            'contact_number' => '09110000158',
            'email' => 'staff-logged-client-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Quezon City',
            'subject' => 'Closed client-requester internal history ticket',
            'description' => 'Client requester ticket logged by staff.',
            'priority' => 'medium',
            'status' => 'closed',
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'creation_source' => Ticket::CREATION_SOURCE_STAFF_FOR_CLIENT,
            'created_by_user_id' => $staffCreator->id,
            'resolved_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
            'user_id' => $client->id,
            'assigned_to' => $staffAssignee->id,
            'category_id' => $category->id,
        ]);

        $staffHistoryTicket = Ticket::create([
            'name' => 'Staff History Requester',
            'contact_number' => '09110000154',
            'email' => 'staff-history-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Closed staff history ticket',
            'description' => 'Staff ticket in history.',
            'priority' => 'medium',
            'status' => 'closed',
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'resolved_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
            'user_id' => $staffRequester->id,
            'assigned_to' => $staffAssignee->id,
            'category_id' => $category->id,
        ]);

        $staffResponse = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'history',
            'ticket_type' => Ticket::TYPE_INTERNAL,
        ]));

        $staffResponse->assertOk();
        $staffResponse->assertSee(route('admin.tickets.show', $staffHistoryTicket), false);
        $staffResponse->assertDontSee(route('admin.tickets.show', $clientHistoryTicket), false);
        $staffResponse->assertDontSee(route('admin.tickets.show', $staffLoggedClientHistoryTicket), false);
        $staffResponse->assertSee('name="ticket_type"', false);
        $staffResponse->assertSee('<option value="'.Ticket::TYPE_INTERNAL.'" selected', false);

        $clientResponse = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'history',
            'ticket_type' => Ticket::TYPE_EXTERNAL,
        ]));

        $clientResponse->assertOk();
        $clientResponse->assertSee(route('admin.tickets.show', $clientHistoryTicket), false);
        $clientResponse->assertSee(route('admin.tickets.show', $staffLoggedClientHistoryTicket), false);
        $clientResponse->assertDontSee(route('admin.tickets.show', $staffHistoryTicket), false);
    }

    public function test_history_results_show_requester_and_creation_path_context(): void
    {
        $supportUser = $this->createSupportUser();
        $staffCreator = $this->createAssignedSupportUser('History Staff Creator', 'history-staff-creator@example.com');
        $staffRequester = $this->createAssignedSupportUser('History Staff Requester Display', 'history-staff-requester-display@example.com');
        $category = $this->createCategory();
        $client = $this->createClient('History Display Client', 'history-display-client@example.com');

        Ticket::create([
            'name' => 'Client Self Requester',
            'contact_number' => '09110000155',
            'email' => 'client-self-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Client self-submitted history ticket',
            'description' => 'Client submitted directly.',
            'priority' => 'medium',
            'status' => 'closed',
            'ticket_type' => Ticket::TYPE_EXTERNAL,
            'creation_source' => Ticket::CREATION_SOURCE_CLIENT_SELF_SERVICE,
            'created_by_user_id' => $client->id,
            'resolved_at' => now()->subDay(),
            'closed_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::create([
            'name' => 'Client Staff Logged Requester',
            'contact_number' => '09110000156',
            'email' => 'client-staff-logged-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Staff logged client history ticket',
            'description' => 'Staff created for client.',
            'priority' => 'medium',
            'status' => 'closed',
            'ticket_type' => Ticket::TYPE_EXTERNAL,
            'creation_source' => Ticket::CREATION_SOURCE_STAFF_FOR_CLIENT,
            'created_by_user_id' => $staffCreator->id,
            'resolved_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::create([
            'name' => 'Staff Logged Staff Requester',
            'contact_number' => '09110000157',
            'email' => 'staff-logged-staff-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Staff logged internal history ticket',
            'description' => 'Staff created for staff.',
            'priority' => 'medium',
            'status' => 'closed',
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'creation_source' => Ticket::CREATION_SOURCE_STAFF_FOR_STAFF,
            'created_by_user_id' => $staffCreator->id,
            'resolved_at' => now()->subDays(3),
            'closed_at' => now()->subDays(2),
            'user_id' => $staffRequester->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'history',
        ]));

        $response->assertOk();
        $response->assertSeeText('Requester: History Display Client');
        $response->assertSeeText('Submitted directly by client');
        $response->assertSeeText('Logged by History Staff Creator for client');
        $response->assertSeeText('Requester: History Staff Requester Display');
        $response->assertSeeText('Logged by History Staff Creator for staff');
    }

    public function test_admin_ticket_heartbeat_keeps_same_page_token_for_off_page_changes(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Heartbeat Client', 'heartbeat-client@example.com');
        $oldestTicket = null;

        for ($index = 1; $index <= 16; $index++) {
            $ticket = Ticket::create([
                'name' => "Heartbeat Requester {$index}",
                'contact_number' => '09110000'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'email' => "heartbeat-requester-{$index}@example.com",
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => "Heartbeat ticket {$index}",
                'description' => 'Heartbeat ticket description.',
                'priority' => 'medium',
                'status' => 'open',
                'user_id' => $client->id,
                'category_id' => $category->id,
            ]);
            Ticket::query()->whereKey($ticket->id)->update([
                'created_at' => Carbon::create(2026, 3, 1, 8, 0, 0)->addMinutes($index),
                'updated_at' => Carbon::create(2026, 3, 1, 8, 0, 0)->addMinutes($index),
            ]);

            if ($index === 1) {
                $oldestTicket = $ticket;
            }
        }

        $initialResponse = $this->actingAs($supportUser)->getJson(route('admin.tickets.index', [
            'tab' => 'tickets',
            'partial' => '1',
        ]));
        $initialResponse->assertOk();

        $initialToken = (string) $initialResponse->json('token');
        $initialPageToken = (string) $initialResponse->json('page_token');

        $this->assertNotSame('', $initialToken);
        $this->assertNotSame('', $initialPageToken);

        Ticket::query()->whereKey($oldestTicket?->id)->update([
            'subject' => 'Updated off-page heartbeat ticket',
            'updated_at' => Carbon::create(2026, 3, 2, 8, 0, 0),
        ]);

        $heartbeatResponse = $this->actingAs($supportUser)->getJson(route('admin.tickets.index', [
            'tab' => 'tickets',
            'heartbeat' => '1',
        ]));
        $heartbeatResponse->assertOk();

        $heartbeatResponse->assertJsonPath('page_token', $initialPageToken);
        $this->assertNotSame($initialToken, (string) $heartbeatResponse->json('token'));
    }

    public function test_admin_ticket_heartbeat_changes_page_token_when_visible_ticket_changes_without_global_snapshot_change(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Visible Heartbeat Client', 'visible-heartbeat-client@example.com');
        $visibleTicket = null;

        for ($index = 1; $index <= 16; $index++) {
            $ticket = Ticket::create([
                'name' => "Visible Heartbeat Requester {$index}",
                'contact_number' => '09120000'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'email' => "visible-heartbeat-requester-{$index}@example.com",
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => "Visible heartbeat ticket {$index}",
                'description' => 'Visible heartbeat ticket description.',
                'priority' => 'medium',
                'status' => 'open',
                'user_id' => $client->id,
                'category_id' => $category->id,
            ]);
            Ticket::query()->whereKey($ticket->id)->update([
                'created_at' => Carbon::create(2026, 3, 1, 9, 0, 0)->addMinutes($index),
                'updated_at' => Carbon::create(2026, 3, 1, 9, 0, 0)->addMinutes($index),
            ]);

            if ($index === 2) {
                $visibleTicket = $ticket;
            }
        }

        $initialResponse = $this->actingAs($supportUser)->getJson(route('admin.tickets.index', [
            'tab' => 'tickets',
            'partial' => '1',
        ]));
        $initialResponse->assertOk();

        $initialToken = (string) $initialResponse->json('token');
        $initialPageToken = (string) $initialResponse->json('page_token');

        $this->assertNotSame('', $initialToken);
        $this->assertNotSame('', $initialPageToken);

        Ticket::query()->whereKey($visibleTicket?->id)->update([
            'subject' => 'Updated visible heartbeat ticket',
            'updated_at' => Carbon::create(2026, 3, 1, 9, 15, 30),
        ]);

        $heartbeatResponse = $this->actingAs($supportUser)->getJson(route('admin.tickets.index', [
            'tab' => 'tickets',
            'heartbeat' => '1',
        ]));
        $heartbeatResponse->assertOk();

        $heartbeatResponse->assertJsonPath('token', $initialToken);
        $this->assertNotSame($initialPageToken, (string) $heartbeatResponse->json('page_token'));
    }

    public function test_technician_does_not_see_needs_attention_tab_even_when_requested(): void
    {
        $technician = $this->createAssignedSupportUser('Scoped Technician', 'scoped-technician@example.com');
        $category = $this->createCategory();
        $client = $this->createClient('Technician Client', 'technician-client@example.com');

        $ticket = Ticket::create([
            'name' => 'Technician Requester',
            'contact_number' => '09110000161',
            'email' => 'technician-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Assigned open ticket',
            'description' => 'Visible to the assigned technician.',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => $technician->id,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($technician)->get(route('admin.tickets.index', [
            'tab' => 'attention',
        ]));

        $response->assertOk();
        $response->assertDontSee('Needs Attention');
        $response->assertSee(route('admin.tickets.show', $ticket), false);
    }

    public function test_breached_tab_only_shows_open_tickets_past_the_sla_resolution_target(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 25, 12, 0, 0));

        try {
            $supportUser = $this->createSupportUser();
            $category = $this->createCategory();
            $client = $this->createClient('Breached Tab Client', 'breached-tab-client@example.com');

            $breachedTicket = Ticket::create([
                'name' => 'Breached Requester',
                'contact_number' => '09110000163',
                'email' => 'breached-requester@example.com',
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => 'Ticket past the SLA target',
                'description' => 'Still open and already past 240 minutes.',
                'priority' => 'medium',
                'status' => 'in_progress',
                'user_id' => $client->id,
                'category_id' => $category->id,
            ]);
            Ticket::query()->whereKey($breachedTicket->id)->update([
                'created_at' => now()->subMinutes(250),
                'updated_at' => now()->subMinutes(10),
            ]);

            $withinTargetTicket = Ticket::create([
                'name' => 'Within Target Requester',
                'contact_number' => '09110000164',
                'email' => 'within-target-requester@example.com',
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => 'Ticket still within the SLA target',
                'description' => 'Open but not yet breached.',
                'priority' => 'medium',
                'status' => 'open',
                'user_id' => $client->id,
                'category_id' => $category->id,
            ]);
            Ticket::query()->whereKey($withinTargetTicket->id)->update([
                'created_at' => now()->subMinutes(100),
                'updated_at' => now()->subMinutes(10),
            ]);

            $resolvedLateTicket = Ticket::create([
                'name' => 'Resolved Late Requester',
                'contact_number' => '09110000165',
                'email' => 'resolved-late-requester@example.com',
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => 'Resolved after breaching the target',
                'description' => 'Already resolved, so it should not clutter the active breached tab.',
                'priority' => 'medium',
                'status' => 'resolved',
                'resolved_at' => now()->subMinutes(5),
                'user_id' => $client->id,
                'category_id' => $category->id,
            ]);
            Ticket::query()->whereKey($resolvedLateTicket->id)->update([
                'created_at' => now()->subMinutes(300),
                'updated_at' => now()->subMinutes(5),
            ]);

            $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
                'tab' => 'breached',
            ]));

            $response->assertOk();
            $response->assertSee(route('admin.tickets.show', $breachedTicket), false);
            $response->assertDontSee(route('admin.tickets.show', $withinTargetTicket), false);
            $response->assertDontSee(route('admin.tickets.show', $resolvedLateTicket), false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_ticket_index_renders_sla_age_badge(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 25, 12, 0, 0));

        try {
            $supportUser = $this->createSupportUser();
            $category = $this->createCategory();
            $client = $this->createClient('SLA Badge Client', 'sla-badge-client@example.com');

            $ticket = Ticket::create([
                'name' => 'SLA Badge Requester',
                'contact_number' => '09110000162',
                'email' => 'sla-badge-requester@example.com',
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => 'Ticket nearing SLA',
                'description' => 'Should render an SLA risk chip.',
                'priority' => 'medium',
                'status' => 'in_progress',
                'user_id' => $client->id,
                'category_id' => $category->id,
            ]);
            Ticket::query()->whereKey($ticket->id)->update([
                'created_at' => now()->subMinutes(211),
                'updated_at' => now()->subMinutes(10),
            ]);

            $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
                'tab' => 'tickets',
            ]));

            $response->assertOk();
            $response->assertSee('SLA risk - 3h 31m');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_location_filters_match_the_whole_value_and_treat_wildcards_literally(): void
    {
        $supportUser = $this->createSupportUser('Super Location', 'super-location-exact@example.com');
        $category = $this->createCategory();
        $pasigTicket = $this->createTicket($this->createClient(), $category, ['municipality' => 'Pasig']);
        $cebuTicket = $this->createTicket($this->createClient('Cebu Client', 'cebu-exact@example.com'), $category, [
            'municipality' => 'Cebu City',
        ]);

        foreach (['%', '_ebu City', 'Cebu'] as $value) {
            $this->actingAs($supportUser)
                ->get(route('admin.tickets.index', ['municipality' => $value]))
                ->assertOk()
                ->assertDontSee(route('admin.tickets.show', $cebuTicket), false)
                ->assertDontSee(route('admin.tickets.show', $pasigTicket), false);
        }

        $this->actingAs($supportUser)
            ->get(route('admin.tickets.index', ['municipality' => 'pasig']))
            ->assertOk()
            ->assertSee(route('admin.tickets.show', $pasigTicket), false)
            ->assertDontSee(route('admin.tickets.show', $cebuTicket), false);
    }
}
