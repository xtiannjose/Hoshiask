<?php

namespace Tests\Unit;

use App\Models\TicketReply;
use App\Models\User;
use App\Services\Admin\ManagedUserDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ManagedUserDeletionServiceTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private function service(): ManagedUserDeletionService
    {
        return app(ManagedUserDeletionService::class);
    }

    public function test_deleting_a_client_reassigns_their_tickets_to_the_deleted_client_placeholder(): void
    {
        $client = $this->createClient('Departing Client', 'departing-client@example.com');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);

        $result = $this->service()->delete($client);

        $ticket->refresh();
        $placeholder = User::where('email', 'deleted.client@system.local')->sole();

        $this->assertSame($placeholder->id, $ticket->user_id);
        $this->assertSame($client->id, $result['deleted_user_id']);
        $this->assertFalse($placeholder->is_active);
        $this->assertDatabaseMissing('users', ['id' => $client->id]);
    }

    public function test_deleting_a_support_user_reassigns_their_tickets_and_replies_to_the_deleted_support_placeholder(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $technical = $this->createAssignedSupportUser('Departing Tech', 'departing-tech@example.com');
        $ticket = $this->createTicket($client, $category, [
            'closed_by' => $technical->id,
        ]);
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $technical->id,
            'message' => 'On it.',
        ]);

        $this->service()->delete($technical);

        $ticket->refresh();
        $reply->refresh();
        $placeholder = User::where('email', 'deleted.support@system.local')->sole();

        $this->assertSame($placeholder->id, $ticket->closed_by);
        $this->assertSame($placeholder->id, $reply->user_id);
        $this->assertFalse($placeholder->is_active);
    }

    public function test_deleting_a_users_primary_assignment_falls_back_to_next_co_assignee(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $leavingTech = $this->createAssignedSupportUser('Leaving Tech', 'leaving-tech@example.com');
        $remainingTech = $this->createAssignedSupportUser('Remaining Tech', 'remaining-tech@example.com');
        $ticket = $this->createTicket($client, $category, [
            'assigned_to' => $leavingTech->id,
        ]);
        $ticket->assignedUsers()->sync([$leavingTech->id, $remainingTech->id]);

        $this->service()->delete($leavingTech);

        $ticket->refresh();

        $this->assertSame($remainingTech->id, $ticket->assigned_to);
        $this->assertDatabaseMissing('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'user_id' => $leavingTech->id,
        ]);
    }

    public function test_deleting_a_sole_assignee_clears_the_primary_assignment(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $soleTech = $this->createAssignedSupportUser('Sole Tech', 'sole-tech@example.com');
        $ticket = $this->createTicket($client, $category, [
            'assigned_to' => $soleTech->id,
        ]);
        $ticket->assignedUsers()->sync([$soleTech->id]);

        $this->service()->delete($soleTech);

        $ticket->refresh();

        $this->assertNull($ticket->assigned_to);
    }

    public function test_repeated_deletions_reuse_the_same_placeholder_account(): void
    {
        $clientOne = $this->createClient('Client One', 'client-one@example.com');
        $clientTwo = $this->createClient('Client Two', 'client-two@example.com');
        $category = $this->createCategory();
        $ticketOne = $this->createTicket($clientOne, $category);
        $ticketTwo = $this->createTicket($clientTwo, $category);

        $this->service()->delete($clientOne);
        $this->service()->delete($clientTwo);

        $ticketOne->refresh();
        $ticketTwo->refresh();

        $this->assertSame($ticketOne->user_id, $ticketTwo->user_id);
        $this->assertSame(1, User::where('email', 'deleted.client@system.local')->count());
    }
}
