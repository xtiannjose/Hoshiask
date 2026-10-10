<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TechnicalTicketVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_ticket_index_lists_assigned_tickets_only(): void
    {
        [$technical, $client, $category] = $this->seedActorData();
        $assignedTechnical = User::create([
            'name' => 'Requester Assignee',
            'email' => 'requester-assignee@example.com',
            'phone' => '09130000010',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $assignedTicket = Ticket::create([
            'name' => 'Assigned Requester',
            'contact_number' => '09130000001',
            'email' => 'assigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Assigned ticket',
            'description' => 'Visible to technical',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'assigned_to' => $technical->id,
            'category_id' => $category->id,
        ]);

        $unassignedTicket = Ticket::create([
            'name' => 'Unassigned Requester',
            'contact_number' => '09130000002',
            'email' => 'unassigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Unassigned ticket',
            'description' => 'Should be hidden',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $internalRequesterTicket = Ticket::create([
            'name' => 'Internal Requester',
            'contact_number' => '09130000011',
            'email' => 'internal-requester-visible@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Own internal requester ticket',
            'description' => 'Should be visible for tracking.',
            'priority' => 'medium',
            'status' => 'open',
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'user_id' => $technical->id,
            'assigned_to' => $assignedTechnical->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($technical)->get(route('admin.tickets.index'));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $assignedTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $internalRequesterTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $unassignedTicket), false);
    }

    public function test_technical_user_can_read_but_not_update_an_unassigned_ticket(): void
    {
        [$technical, $client, $category] = $this->seedActorData();

        $unassignedTicket = Ticket::create([
            'name' => 'Unassigned Requester',
            'contact_number' => '09130000003',
            'email' => 'unassigned-route-check@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Unassigned route check',
            'description' => 'Should be forbidden',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        // A colleague's client ticket can be opened (read-only, e.g. from a shared link)...
        $showResponse = $this->actingAs($technical)->get(route('admin.tickets.show', $unassignedTicket));
        $showResponse->assertOk()->assertSee('data-read-only-notice', false);

        // ...but not changed.

        $statusResponse = $this->actingAs($technical)->post(route('admin.tickets.status', $unassignedTicket), [
            'status' => 'in_progress',
        ]);
        $statusResponse->assertForbidden();
    }

    public function test_secondary_assigned_technician_can_view_shared_ticket(): void
    {
        [$primaryTechnical, $client, $category] = $this->seedActorData();
        $secondaryTechnical = User::create([
            'name' => 'Shared Tech',
            'email' => 'shared-tech@example.com',
            'phone' => '09130000004',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $sharedTicket = Ticket::create([
            'name' => 'Shared Requester',
            'contact_number' => '09130000005',
            'email' => 'shared-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Shared technician ticket',
            'description' => 'Visible to both assigned technicians.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'assigned_to' => $primaryTechnical->id,
            'category_id' => $category->id,
        ]);
        $sharedTicket->assignedUsers()->sync([$primaryTechnical->id, $secondaryTechnical->id]);

        $indexResponse = $this->actingAs($secondaryTechnical)->get(route('admin.tickets.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee(route('admin.tickets.show', $sharedTicket), false);

        $showResponse = $this->actingAs($secondaryTechnical)->get(route('admin.tickets.show', $sharedTicket));
        $showResponse->assertOk();
    }

    public function test_technical_requester_can_view_closed_internal_ticket_but_cannot_manage_it(): void
    {
        [$technical, $client, $category] = $this->seedActorData();
        $assignedTechnical = User::create([
            'name' => 'Other Assigned Tech',
            'email' => 'other-assigned-tech@example.com',
            'phone' => '09130000012',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $internalRequesterTicket = Ticket::create([
            'name' => 'Internal Requester',
            'contact_number' => '09130000013',
            'email' => 'internal-requester-open-visible@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Closed internal requester visibility',
            'description' => 'Requester can view but not manage.',
            'priority' => 'medium',
            'status' => 'closed',
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'user_id' => $technical->id,
            'assigned_to' => $assignedTechnical->id,
            'category_id' => $category->id,
            'resolved_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
        ]);

        $showResponse = $this->actingAs($technical)->get(route('admin.tickets.show', $internalRequesterTicket));
        $showResponse->assertOk();

        $statusResponse = $this->actingAs($technical)->post(route('admin.tickets.status', $internalRequesterTicket), [
            'status' => 'in_progress',
        ]);
        $statusResponse->assertForbidden();

        $replyResponse = $this->actingAs($technical)->post(route('admin.tickets.reply', $internalRequesterTicket), [
            'message' => 'Checking in on this request.',
        ]);
        $replyResponse->assertForbidden();
    }

    public function test_assigned_technical_user_can_see_client_rating_feedback(): void
    {
        [$technical, $client, $category] = $this->seedActorData();

        $ticket = Ticket::create([
            'name' => 'Rated Requester',
            'contact_number' => '09130000006',
            'email' => 'rated-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Rated ticket',
            'description' => 'Client left feedback.',
            'priority' => 'medium',
            'status' => 'closed',
            'user_id' => $client->id,
            'assigned_to' => $technical->id,
            'category_id' => $category->id,
            'resolved_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
            'satisfaction_rating' => 4,
            'satisfaction_comment' => 'Good job',
        ]);

        $response = $this->actingAs($technical)->get(route('admin.tickets.show', $ticket));

        $response->assertOk();
        $response->assertSeeText('Client Rating');
        $response->assertSeeText('4 / 5');
        $response->assertSeeText('Good job');
    }

    public function test_technical_history_includes_own_closed_internal_requests_only(): void
    {
        [$technical, $client, $category] = $this->seedActorData();
        $assignedTechnical = User::create([
            'name' => 'History Assignee',
            'email' => 'history-assignee@example.com',
            'phone' => '09130000007',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $closedInternalTicket = Ticket::create([
            'name' => 'Internal Requester',
            'contact_number' => '09130000008',
            'email' => 'internal-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Closed internal history ticket',
            'description' => 'Should appear in the requester history tab.',
            'priority' => 'medium',
            'status' => 'closed',
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'user_id' => $technical->id,
            'assigned_to' => $assignedTechnical->id,
            'category_id' => $category->id,
            'resolved_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
        ]);

        $openInternalTicket = Ticket::create([
            'name' => 'Internal Requester',
            'contact_number' => '09130000009',
            'email' => 'internal-requester-open@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Open internal ticket',
            'description' => 'Should stay out of requester history.',
            'priority' => 'medium',
            'status' => 'open',
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'user_id' => $technical->id,
            'assigned_to' => $assignedTechnical->id,
            'category_id' => $category->id,
        ]);

        $historyResponse = $this->actingAs($technical)->get(route('admin.tickets.index', [
            'tab' => 'history',
        ]));

        $historyResponse->assertOk();
        $historyResponse->assertSee(route('admin.tickets.show', $closedInternalTicket), false);
        $historyResponse->assertDontSee(route('admin.tickets.show', $openInternalTicket), false);

        $allResponse = $this->actingAs($technical)->get(route('admin.tickets.index', [
            'tab' => 'all',
        ]));

        $allResponse->assertOk();
        $allResponse->assertDontSee(route('admin.tickets.show', $closedInternalTicket), false);

        $showClosedResponse = $this->actingAs($technical)->get(route('admin.tickets.show', $closedInternalTicket));
        $showClosedResponse->assertOk();

        $showOpenResponse = $this->actingAs($technical)->get(route('admin.tickets.show', $openInternalTicket));
        $showOpenResponse->assertForbidden();
    }

    private function seedActorData(): array
    {
        $technical = User::create([
            'name' => 'Scoped Tech',
            'email' => 'scoped-tech@example.com',
            'phone' => '09130000000',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Scoped Client',
            'email' => 'scoped-client@example.com',
            'phone' => '09131112222',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Scoped Category',
            'description' => 'Scoped test category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        return [$technical, $client, $category];
    }
}
