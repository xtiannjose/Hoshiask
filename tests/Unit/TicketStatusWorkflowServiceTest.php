<?php

namespace Tests\Unit;

use App\Services\Admin\TicketStatusWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TicketStatusWorkflowServiceTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private function service(): TicketStatusWorkflowService
    {
        return app(TicketStatusWorkflowService::class);
    }

    public function test_gate_allows_reverting_a_ticket_closed_within_the_window(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, [
            'status' => 'closed',
            'closed_at' => now()->subDays(6),
        ]);

        $this->assertNull($this->service()->reopenClosedStatusGateErrorForTicket($ticket, 'in_progress'));
    }

    public function test_gate_blocks_reverting_a_ticket_closed_past_the_window(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, [
            'status' => 'closed',
            'closed_at' => now()->subDays(8),
        ]);

        $error = $this->service()->reopenClosedStatusGateErrorForTicket($ticket, 'in_progress');

        $this->assertNotNull($error);
        $this->assertStringContainsString('can no longer be reverted', $error);
    }

    public function test_gate_allows_at_exactly_the_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-08 00:00:00'));
        $client = $this->createClient();
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, [
            'status' => 'closed',
            'closed_at' => Carbon::parse('2026-01-01 00:00:00'),
        ]);

        $this->assertNull($this->service()->reopenClosedStatusGateErrorForTicket($ticket, 'in_progress'));

        Carbon::setTestNow();
    }

    public function test_gate_is_a_no_op_for_tickets_that_are_not_closed(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, [
            'status' => 'open',
        ]);

        $this->assertNull($this->service()->reopenClosedStatusGateErrorForTicket($ticket, 'in_progress'));
    }

    public function test_gate_is_a_no_op_when_next_status_is_also_closed(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, [
            'status' => 'closed',
            'closed_at' => now()->subDays(30),
        ]);

        $this->assertNull($this->service()->reopenClosedStatusGateErrorForTicket($ticket, 'closed'));
    }

    public function test_gate_is_a_no_op_when_next_status_is_null(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, [
            'status' => 'closed',
            'closed_at' => now()->subDays(30),
        ]);

        $this->assertNull($this->service()->reopenClosedStatusGateErrorForTicket($ticket, null));
    }

    public function test_gate_allows_when_closed_at_is_missing(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, [
            'status' => 'closed',
            'closed_at' => null,
        ]);

        $this->assertNull($this->service()->reopenClosedStatusGateErrorForTicket($ticket, 'in_progress'));
    }

    public function test_a_bulk_cancel_is_refused_for_a_ticket_that_moved_on_after_the_caller_checked_it(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        // The caller saw this ticket as Open, so cancelling passed its check. Meanwhile work started on it.
        $ticket = $this->createTicket($client, $category, ['status' => 'in_progress']);

        $request = Request::create('/', 'POST', ['status' => 'cancelled', 'close_reason' => 'Not needed']);

        try {
            $this->service()->bulkStatusTickets($request, [$ticket->id]);
            $this->fail('The bulk cancel should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('can only be cancelled while it is Open or Pending', $exception->validator->errors()->first());
        }

        $this->assertSame('in_progress', $ticket->fresh()->status);
    }
}
