<?php

namespace Tests\Feature;

use App\Mail\PendingFollowUpMail;
use App\Mail\TicketAlertMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PendingFollowUpTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $client;

    private User $technical;

    private $category;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 8, 0, 0));
        Mail::fake();
        config(['hoshiask.pending_follow_up_days' => 3]);

        $this->client = $this->createUser('Client Pending', 'client-pending@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $this->technical = $this->createUser('Tech Pending', 'tech-pending@example.com', User::ROLE_TECHNICAL);
        $this->category = $this->createCategory();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_quiet_pending_ticket_is_listed_in_one_email_to_its_assignee_and_not_to_the_client(): void
    {
        $quiet = $this->pendingTicket(daysQuiet: 4, assignedTo: $this->technical);
        $busy = $this->pendingTicket(daysQuiet: 1, assignedTo: $this->technical);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertQueued(PendingFollowUpMail::class, 1);
        Mail::assertQueued(PendingFollowUpMail::class, function (PendingFollowUpMail $mail) use ($quiet, $busy) {
            return $mail->hasTo($this->technical->email)
                && $mail->total === 1
                && $mail->tickets[0]['ticket_number'] === $quiet->ticket_number
                && ! str_contains(json_encode($mail->tickets), $busy->ticket_number);
        });
        // The client hears nothing about it, and the ticket stays Pending.
        $this->assertSame(0, Mail::queued(TicketAlertMail::class)->count());
        $this->assertSame('pending', $quiet->fresh()->status);
        $this->assertNotNull($quiet->fresh()->staff_notified_pending_at);
        $this->assertNull($busy->fresh()->staff_notified_pending_at);
    }

    public function test_several_quiet_tickets_of_one_person_arrive_as_a_single_email(): void
    {
        $this->pendingTicket(daysQuiet: 4, assignedTo: $this->technical);
        $this->pendingTicket(daysQuiet: 6, assignedTo: $this->technical);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertQueued(PendingFollowUpMail::class, 1);
        Mail::assertQueued(PendingFollowUpMail::class, fn (PendingFollowUpMail $mail) => $mail->total === 2 && count($mail->tickets) === 2);
    }

    public function test_the_same_ticket_is_not_listed_again_until_another_period_has_passed(): void
    {
        $this->pendingTicket(daysQuiet: 4, assignedTo: $this->technical);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();
        Carbon::setTestNow(now()->addDays(2));
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertQueued(PendingFollowUpMail::class, 1);

        Carbon::setTestNow(now()->addDays(2));
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertQueued(PendingFollowUpMail::class, 2);
    }

    public function test_a_reply_inside_the_period_counts_as_activity(): void
    {
        $ticket = $this->pendingTicket(daysQuiet: 5, assignedTo: $this->technical);
        $this->replyDaysAgo($ticket, $this->client, 1);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertNotQueued(PendingFollowUpMail::class);
        $this->assertNull($ticket->fresh()->staff_notified_pending_at);
    }

    public function test_an_old_reply_does_not_hide_a_ticket_that_has_gone_quiet(): void
    {
        $ticket = $this->pendingTicket(daysQuiet: 8, assignedTo: $this->technical);
        $this->replyDaysAgo($ticket, $this->technical, 5);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertQueued(PendingFollowUpMail::class, fn (PendingFollowUpMail $mail) => str_contains($mail->tickets[0]['waiting'], '5 days'));
    }

    public function test_a_pending_ticket_with_nobody_assigned_goes_to_the_super_users(): void
    {
        $superUser = $this->createUser('Super Pending', 'super-pending@example.com', User::ROLE_SUPER_USER);
        $this->pendingTicket(daysQuiet: 4);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertQueued(PendingFollowUpMail::class, 1);
        Mail::assertQueued(PendingFollowUpMail::class, fn (PendingFollowUpMail $mail) => $mail->hasTo($superUser->email));
        Mail::assertNotQueued(PendingFollowUpMail::class, fn (PendingFollowUpMail $mail) => $mail->hasTo($this->technical->email));
    }

    public function test_imported_and_other_status_tickets_are_left_alone(): void
    {
        $this->createUser('Super Pending', 'super-pending@example.com', User::ROLE_SUPER_USER);
        $this->pendingTicket(daysQuiet: 20, attributes: ['is_imported' => true, 'creation_source' => Ticket::CREATION_SOURCE_IMPORTED]);
        $this->pendingTicket(daysQuiet: 20, attributes: ['status' => 'in_progress']);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertNotQueued(PendingFollowUpMail::class);
    }

    public function test_setting_the_days_to_zero_switches_the_reminder_off(): void
    {
        config(['hoshiask.pending_follow_up_days' => 0]);
        $this->pendingTicket(daysQuiet: 20, assignedTo: $this->technical);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertNotQueued(PendingFollowUpMail::class);
    }

    public function test_the_email_carries_a_link_to_the_ticket_and_no_client_wording(): void
    {
        $ticket = $this->pendingTicket(daysQuiet: 4, assignedTo: $this->technical);

        $mail = new PendingFollowUpMail('Tech Pending', [[
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'waiting' => '4 days with no activity',
            'url' => route('admin.tickets.show', $ticket),
        ]], 1, 3);

        $html = $mail->render();

        $this->assertStringContainsString($ticket->ticket_number, $html);
        $this->assertStringContainsString(route('admin.tickets.show', $ticket), $html);
        $this->assertStringContainsString('The client has not been emailed.', $html);
        $this->assertSame('Follow up: 1 pending ticket has had no activity for 3 days', $mail->envelope()->subject);
    }

    private function replyDaysAgo(Ticket $ticket, User $author, int $days): void
    {
        $reply = $ticket->replies()->create([
            'user_id' => $author->id,
            'message' => 'Please send the serial number.',
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
