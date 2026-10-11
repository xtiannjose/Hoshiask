<?php

namespace Tests\Feature;

use App\Events\ProductionAdvisoriesRequested;
use App\Listeners\ReportPendingTicketAdvisories;
use App\Mail\PendingFollowUpMail;
use App\Mail\TicketAlertMail;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PendingAutoCancelTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $client;

    private User $technical;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 8, 0, 0));
        Mail::fake();
        config(['helpdesk.pending_auto_cancel_days' => 14, 'helpdesk.pending_follow_up_days' => 3]);

        $this->client = $this->createUser('Client Cancel', 'client-cancel@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $this->technical = $this->createUser('Tech Cancel', 'tech-cancel@example.com', User::ROLE_TECHNICAL);
        $this->category = $this->createCategory();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_pending_ticket_quiet_for_the_period_is_cancelled_with_its_reason_and_the_client_is_not_emailed(): void
    {
        $ticket = $this->pendingTicket(daysQuiet: 15);

        $this->artisan('tickets:auto-cancel-pending')->assertSuccessful();

        $ticket->refresh();
        $this->assertSame('cancelled', $ticket->status);
        $this->assertNotNull($ticket->cancelled_at);
        $this->assertSame('Automatically cancelled after 14 days with no activity while Pending.', $ticket->cancellation_reason);
        $this->assertTrue(
            $ticket->replies()->where('is_internal', true)->where('message', 'like', '%Automatically cancelled after 14 days%')->exists()
        );
        Mail::assertNotQueued(TicketAlertMail::class);
    }

    public function test_tickets_that_are_not_quiet_long_enough_are_left_alone(): void
    {
        $recent = $this->pendingTicket(daysQuiet: 10);
        $replied = $this->pendingTicket(daysQuiet: 20);
        $this->replyDaysAgo($replied, $this->client, 2);
        $working = $this->pendingTicket(daysQuiet: 20, attributes: ['status' => 'in_progress']);
        $imported = $this->pendingTicket(daysQuiet: 20, attributes: ['is_imported' => true, 'creation_source' => Ticket::CREATION_SOURCE_IMPORTED]);

        $this->artisan('tickets:auto-cancel-pending')->assertSuccessful();

        $this->assertSame('pending', $recent->fresh()->status);
        $this->assertSame('pending', $replied->fresh()->status);
        $this->assertSame('in_progress', $working->fresh()->status);
        $this->assertSame('pending', $imported->fresh()->status);
    }

    public function test_zero_days_switches_the_cancel_off(): void
    {
        config(['helpdesk.pending_auto_cancel_days' => 0]);
        $ticket = $this->pendingTicket(daysQuiet: 60);

        $this->artisan('tickets:auto-cancel-pending')->assertSuccessful();

        $this->assertSame('pending', $ticket->fresh()->status);
    }

    public function test_a_dry_run_lists_the_tickets_and_changes_nothing(): void
    {
        $ticket = $this->pendingTicket(daysQuiet: 15);

        $this->artisan('tickets:auto-cancel-pending', ['--dry-run' => true])
            ->expectsOutputToContain('Would cancel '.$ticket->ticket_number)
            ->assertSuccessful();

        $this->assertSame('pending', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->cancelled_at);
    }

    public function test_the_follow_up_email_says_when_the_ticket_will_be_cancelled(): void
    {
        $this->pendingTicket(daysQuiet: 11, assignedTo: $this->technical);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertQueued(PendingFollowUpMail::class, fn (PendingFollowUpMail $mail) => $mail->cancelAfterDays === 14
            && str_contains($mail->tickets[0]['waiting'], '11 days with no activity, cancelled automatically in 3 days'));
    }

    public function test_the_status_check_names_a_cancel_that_comes_before_the_reminder(): void
    {
        config(['helpdesk.pending_auto_cancel_days' => 3, 'helpdesk.pending_follow_up_days' => 3]);
        $request = new ProductionAdvisoriesRequested;
        (new ReportPendingTicketAdvisories)->handle($request);
        $this->assertCount(1, $request->advisories());
        $this->assertStringContainsString('not later than the first staff reminder', $request->advisories()[0]);

        config(['helpdesk.pending_auto_cancel_days' => 14, 'helpdesk.pending_follow_up_days' => 0]);
        $request = new ProductionAdvisoriesRequested;
        (new ReportPendingTicketAdvisories)->handle($request);
        $this->assertStringContainsString('staff are not reminded first', $request->advisories()[0]);

        config(['helpdesk.pending_auto_cancel_days' => 14, 'helpdesk.pending_follow_up_days' => 3]);
        $request = new ProductionAdvisoriesRequested;
        (new ReportPendingTicketAdvisories)->handle($request);
        $this->assertSame([], $request->advisories());
    }

    private function replyDaysAgo(Ticket $ticket, User $author, int $days): void
    {
        $reply = $ticket->replies()->create([
            'user_id' => $author->id,
            'message' => 'Still here.',
            'is_internal' => false,
        ]);

        // created_at is not mass-assignable, so back-date it directly.
        $reply->newQuery()->whereKey($reply->id)->update(['created_at' => now()->subDays($days)]);
    }

    private function pendingTicket(int $daysQuiet, ?User $assignedTo = null, array $attributes = []): Ticket
    {
        $ticket = $this->createTicket($this->client, $this->category, array_merge([
            'status' => 'pending',
            'assigned_to' => $assignedTo?->id,
            'assigned_at' => $assignedTo ? now()->subDays($daysQuiet + 1) : null,
        ], $attributes));

        if ($assignedTo) {
            $ticket->assignedUsers()->sync([$assignedTo->id]);
        }

        // Back-date the last activity without going through the model, which would stamp "now".
        Ticket::query()->whereKey($ticket->id)->update([
            'created_at' => now()->subDays($daysQuiet + 1),
            'updated_at' => now()->subDays($daysQuiet),
        ]);

        return $ticket->fresh();
    }
}
