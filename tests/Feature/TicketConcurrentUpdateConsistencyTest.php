<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\Admin\TicketAssignmentService;
use App\Services\Admin\TicketStatusWorkflowService;
use App\Services\TicketAcknowledgmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TicketConcurrentUpdateConsistencyTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    public function test_stale_ticket_instance_does_not_clobber_closed_at_when_reclosing_an_already_closed_ticket(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $technical = $this->createAssignedSupportUser();
        $adminA = $this->createSupportUser('Admin A', 'admin-a@example.com');
        $adminB = $this->createSupportUser('Admin B', 'admin-b@example.com');

        $ticket = $this->createTicket($client, $category, [
            'status' => 'resolved',
            'resolved_at' => now()->subHour(),
        ]);
        $ticket->update(['assigned_to' => $technical->id]);
        $ticket->assignedUsers()->sync([$technical->id]);

        // Two staff members open the same already-resolved ticket at roughly the same time,
        // each holding their own in-memory copy fetched before either one acts.
        $ticketAsSeenByA = Ticket::find($ticket->id);
        $ticketAsSeenByB = Ticket::find($ticket->id);

        $service = app(TicketStatusWorkflowService::class);

        $this->actingAs($adminA);
        $service->updateTicketStatus(
            Request::create('/', 'POST', ['status' => 'closed', 'close_reason' => 'Closed by A.']),
            $ticketAsSeenByA
        );

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertSame($adminA->id, $ticket->closed_by);
        $trueClosedAt = $ticket->closed_at;
        $this->assertNotNull($trueClosedAt);

        // B never saw A's change — B's copy still thinks the ticket is "resolved" with no closed_at.
        $this->actingAs($adminB);
        $result = $service->updateTicketStatus(
            Request::create('/', 'POST', ['status' => 'closed', 'close_reason' => 'Closed by B too.']),
            $ticketAsSeenByB
        );

        $this->assertFalse($result, 'A stale re-close attempt on an already-closed ticket must be treated as a no-op.');

        $ticket->refresh();
        $this->assertSame(
            $adminA->id,
            $ticket->closed_by,
            'The original closer must not be overwritten by a stale concurrent close attempt.'
        );
        $this->assertTrue(
            $ticket->closed_at->equalTo($trueClosedAt),
            'closed_at must not be bumped by a stale concurrent close attempt.'
        );
        $this->assertSame(
            1,
            TicketReply::where('ticket_id', $ticket->id)->where('message', 'like', 'Ticket was closed by%')->count(),
            'Only one closure note should exist — the stale re-close must not append a duplicate.'
        );
    }

    public function test_bulk_status_update_rolls_back_entirely_if_one_ticket_fails_mid_batch(): void
    {
        $this->actingAs($this->createSupportUser());
        $client = $this->createClient();
        $category = $this->createCategory();
        $ticketOne = $this->createTicket($client, $category, ['status' => 'open']);
        $ticketTwo = $this->createTicket($client, $category, ['status' => 'open']);
        $ticketThree = $this->createTicket($client, $category, ['status' => 'open']);

        $this->partialMock(TicketAcknowledgmentService::class, function ($mock) use ($ticketTwo) {
            $mock->shouldReceive('trackHandlingAction')->andReturnUsing(function (Ticket $ticket) use ($ticketTwo) {
                if ($ticket->id === $ticketTwo->id) {
                    throw new RuntimeException('Simulated mid-batch failure.');
                }

                return null;
            });
        });

        $service = app(TicketStatusWorkflowService::class);

        try {
            $service->bulkStatusTickets(
                Request::create('/', 'POST', ['status' => 'in_progress']),
                [$ticketOne->id, $ticketTwo->id, $ticketThree->id]
            );
            $this->fail('Expected the simulated failure to propagate out of the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated mid-batch failure.', $exception->getMessage());
        }

        $this->assertSame('open', Ticket::find($ticketOne->id)->status, 'The ticket processed before the failure must roll back.');
        $this->assertSame('open', Ticket::find($ticketTwo->id)->status);
        $this->assertSame('open', Ticket::find($ticketThree->id)->status, 'The ticket after the failure must never have been touched.');
    }

    public function test_bulk_assign_rolls_back_entirely_if_one_ticket_fails_mid_batch(): void
    {
        $this->actingAs($this->createSupportUser());
        $client = $this->createClient();
        $category = $this->createCategory();
        $technical = $this->createAssignedSupportUser();
        $ticketOne = $this->createTicket($client, $category);
        $ticketTwo = $this->createTicket($client, $category);
        $ticketThree = $this->createTicket($client, $category);

        $this->partialMock(TicketAcknowledgmentService::class, function ($mock) use ($ticketTwo) {
            $mock->shouldReceive('trackHandlingAction')->andReturnUsing(function (Ticket $ticket) use ($ticketTwo) {
                if ($ticket->id === $ticketTwo->id) {
                    throw new RuntimeException('Simulated mid-batch failure.');
                }

                return null;
            });
        });

        $service = app(TicketAssignmentService::class);

        try {
            $service->bulkAssignTickets(
                Request::create('/', 'POST', ['assigned_to' => [$technical->id]]),
                [$ticketOne->id, $ticketTwo->id, $ticketThree->id]
            );
            $this->fail('Expected the simulated failure to propagate out of the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated mid-batch failure.', $exception->getMessage());
        }

        $this->assertNull(Ticket::find($ticketOne->id)->assigned_to, 'The ticket processed before the failure must roll back.');
        $this->assertNull(Ticket::find($ticketTwo->id)->assigned_to);
        $this->assertNull(Ticket::find($ticketThree->id)->assigned_to, 'The ticket after the failure must never have been touched.');
        $this->assertDatabaseMissing('ticket_assignments', ['ticket_id' => $ticketOne->id]);
        $this->assertDatabaseMissing('ticket_assignments', ['ticket_id' => $ticketThree->id]);
    }

    public function test_a_status_change_chosen_from_a_stale_copy_is_refused_when_someone_else_changed_the_status(): void
    {
        $admin = $this->createSupportUser('Admin Stale', 'admin-stale-status@example.com');
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), [
            'status' => 'resolved',
            'resolved_at' => now()->subDays(8),
        ]);

        // Auto-close (or a staff page) looked at the ticket while it was Resolved...
        $ticketAsSeen = Ticket::find($ticket->id);
        // ...then the client reopened it before the close was saved.
        DB::table('tickets')->where('id', $ticket->id)->update(['status' => 'open', 'resolved_at' => null]);

        $this->actingAs($admin);
        try {
            app(TicketStatusWorkflowService::class)->updateTicketStatus(
                Request::create('/', 'POST', ['status' => 'closed', 'close_reason' => 'Automatically closed.']),
                $ticketAsSeen
            );
            $this->fail('Expected the stale change to be refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('was just changed to Open', $exception->errors()['status'][0]);
        }

        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame(0, TicketReply::where('ticket_id', $ticket->id)->count());
    }

    public function test_a_quick_update_from_a_stale_copy_does_not_reopen_a_ticket_someone_resolved(): void
    {
        $admin = $this->createSupportUser('Admin Stale Quick', 'admin-stale-quick@example.com');
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), [
            'status' => 'open',
            'priority' => 'severity_2',
        ]);

        $ticketAsSeen = Ticket::find($ticket->id);
        DB::table('tickets')->where('id', $ticket->id)->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->actingAs($admin);
        $this->expectException(ValidationException::class);
        try {
            app(TicketStatusWorkflowService::class)->quickUpdateTicket(
                Request::create('/', 'POST', ['status' => 'open', 'priority' => 'severity_1', 'assigned_to' => '']),
                $ticketAsSeen
            );
        } finally {
            $this->assertSame('resolved', $ticket->fresh()->status);
            $this->assertSame('severity_2', $ticket->fresh()->priority);
        }
    }

    public function test_auto_close_skips_a_ticket_that_changed_after_it_was_selected(): void
    {
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), [
            'status' => 'resolved',
            'resolved_at' => now()->subDays(8),
            'client_notified_auto_close_warning_at' => now()->subDays(2),
        ]);
        $this->mock(TicketStatusWorkflowService::class)
            ->shouldReceive('updateTicketStatus')
            ->once()
            ->andThrow(ValidationException::withMessages(['status' => 'Changed meanwhile.']));

        $this->artisan('tickets:auto-close-resolved')
            ->expectsOutputToContain('Auto-closed 0 resolved ticket(s)')
            ->assertSuccessful();

        $this->assertSame('resolved', $ticket->fresh()->status);
    }
}
